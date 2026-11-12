<?php

namespace App\Services;

use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\DndEntry;
use App\Models\ListMember;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactImporter
{
    public function phone(string $phone): string
    {
        $phone = strtr($phone, array_combine(preg_split('//u', '০১২৩৪৫৬৭৮৯', -1, PREG_SPLIT_NO_EMPTY), str_split('0123456789')));
        if (preg_match('/[^0-9+\s()\-]/', $phone)) {
            throw ValidationException::withMessages(['phone' => 'Use a valid Bangladesh mobile number.']);
        }
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '00880')) {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) === 11 && str_starts_with($phone, '01')) {
            $phone = '88'.$phone;
        }
        if (! preg_match('/\A8801[3-9][0-9]{8}\z/', $phone)) {
            throw ValidationException::withMessages(['phone' => 'Use a valid Bangladesh mobile number.']);
        }

        return $phone;
    }

    public function import(string $csv, ?int $listId = null): array
    {
        if (strlen($csv) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages(['csv' => 'CSV must be smaller than 2 MB.']);
        }
        if ($listId !== null) {
            ContactList::findOrFail($listId);
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $header = fgetcsv($stream, escape: '');
        if (! $header) {
            throw ValidationException::withMessages(['csv' => 'CSV is empty.']);
        }
        $header = array_map(fn ($h) => strtolower(trim(ltrim($h, "\xEF\xBB\xBF"))), $header);
        if (count(array_unique($header)) !== count($header)) {
            throw ValidationException::withMessages(['csv' => 'CSV headers must not be duplicated.']);
        }
        if (! in_array('phone', $header, true)) {
            throw ValidationException::withMessages(['csv' => 'A header row with phone is required.']);
        }
        $rows = [];
        while (($values = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $values;
            if (count($rows) > 1000) {
                throw ValidationException::withMessages(['csv' => 'Import at most 1,000 rows at a time.']);
            }
        }
        fclose($stream);
        $result = ['created' => 0, 'updated' => 0, 'invalid' => 0, 'errors' => []];
        foreach ($rows as $index => $values) {
            $line = $index + 2;
            if ($values === [null]) {
                continue;
            }
            try {
                if (count($values) !== count($header)) {
                    throw new \InvalidArgumentException('Column count mismatch');
                }
                $row = array_combine($header, $values);
                $phone = $this->phone((string) $row['phone']);
                $classValue = trim($row['student_class'] ?? '');
                if ($classValue !== '' && ! preg_match('/\A[1-5]\z/', $classValue)) {
                    throw new \InvalidArgumentException('Student class must be 1–5');
                }
                $class = $classValue !== '' ? (int) $classValue : null;
                $version = trim($row['version'] ?? '');
                if (! in_array($version, ['', 'Bangla', 'English', 'BV', 'EV'], true)) {
                    throw new \InvalidArgumentException('Version must be Bangla, English, BV or EV');
                }
                $leadAt = ! empty($row['lead_at']) ? CarbonImmutable::parse($row['lead_at'], app(TenantContext::class)->requireTenant()->timezone)->utc() : null;
                if ($leadAt?->isFuture()) {
                    throw new \InvalidArgumentException('Lead timestamp cannot be in the future');
                }
                $isNew = DB::transaction(function () use ($row, $phone, $class, $version, $leadAt, $listId): bool {
                    $contact = Contact::firstOrNew(['phone_e164' => $phone]);
                    $new = ! $contact->exists;
                    $group = $row['experiment_group'] ?? ($contact->experiment_group ?: 'call');
                    if (! in_array($group, ['call', 'control'], true)) {
                        throw new \InvalidArgumentException('Group must be call or control');
                    }
                    $attributes = ['experiment_group' => $group];
                    if (array_key_exists('parent_name', $row) || array_key_exists('name', $row)) {
                        $attributes['name'] = mb_substr($row['parent_name'] ?? $row['name'], 0, 180);
                    }
                    if (array_key_exists('student_class', $row)) {
                        $attributes['student_class'] = $class;
                    }
                    if (array_key_exists('version', $row)) {
                        $attributes['version'] = ['BV' => 'Bangla', 'EV' => 'English'][$version] ?? ($version ?: null);
                    }
                    if (array_key_exists('source', $row) || $new) {
                        $attributes['source'] = mb_substr($row['source'] ?? 'csv_import', 0, 180);
                    }
                    if (array_key_exists('lead_at', $row)) {
                        $attributes['lead_at'] = $leadAt;
                    }
                    if (array_key_exists('aitutor_user_id', $row)) {
                        $attributes['external_ref'] = mb_substr($row['aitutor_user_id'], 0, 255);
                    }
                    $contact->fill($attributes)->save();
                    if ($listId) {
                        ListMember::firstOrCreate(['contact_list_id' => $listId, 'contact_id' => $contact->id]);
                    }
                    foreach (['consent_calls' => 'service', 'consent_marketing' => 'marketing'] as $column => $purpose) {
                        $value = strtolower(trim($row[$column] ?? ''));
                        if ($value === '') {
                            continue;
                        }
                        if (! in_array($value, ['yes', 'no', '1', '0', 'true', 'false'], true)) {
                            throw new \InvalidArgumentException('Consent must be yes or no');
                        }
                        $granted = in_array($value, ['yes', '1', 'true'], true);
                        $occurredAt = ! empty($row['consent_calls_at']) ? CarbonImmutable::parse($row['consent_calls_at'], app(TenantContext::class)->requireTenant()->timezone)->utc() : $leadAt;
                        if ($granted && $occurredAt === null) {
                            throw new \InvalidArgumentException('Granted consent needs consent_calls_at or lead_at');
                        }
                        if ($occurredAt?->isFuture()) {
                            throw new \InvalidArgumentException('Consent timestamp cannot be in the future');
                        }
                        $occurredAt ??= now();
                        $latest = ConsentEvent::where('contact_id', $contact->id)->where('purpose', $purpose)->where('channel', 'call')->orderByDesc('occurred_at')->orderByDesc('id')->first();
                        if ($latest && $latest->occurred_at->gte($occurredAt)) {
                            continue;
                        }
                        ConsentEvent::create(['contact_id' => $contact->id, 'channel' => 'call', 'purpose' => $purpose, 'granted' => $granted, 'source' => $row['source'] ?? 'csv_import', 'text_version' => $row['consent_text_version'] ?? 'imported-declaration-v1', 'occurred_at' => $occurredAt, 'recorded_by' => auth()->id()]);
                    }
                    if (in_array(strtolower($row['dnd'] ?? ''), ['yes', '1', 'true'], true)) {
                        DndEntry::firstOrCreate(['phone_e164' => $phone], ['reason' => 'Opt-out supplied in CSV', 'source' => 'csv_import']);
                    }

                    return $new;
                });
                $result[$isNew ? 'created' : 'updated']++;
            } catch (\Throwable $error) {
                $result['invalid']++;
                $result['errors'][] = ['line' => $line, 'error' => $error instanceof ValidationException ? collect($error->errors())->flatten()->first() : ($error instanceof \InvalidArgumentException ? $error->getMessage() : 'Could not import this row')];
            }
        }

        return $result;
    }

    public function consent(Contact $contact, bool $granted, string $purpose, string $source, string $version): ConsentEvent
    {
        if (! in_array($purpose, ['service', 'marketing'], true)) {
            throw ValidationException::withMessages(['purpose' => 'Invalid purpose']);
        }

        return ConsentEvent::create(['contact_id' => $contact->id, 'channel' => 'call', 'purpose' => $purpose, 'granted' => $granted, 'source' => $source, 'text_version' => $version, 'occurred_at' => now(), 'recorded_by' => auth()->id()]);
    }
}
