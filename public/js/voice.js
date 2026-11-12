window.mrcPreviewVoice = function (text) {
    if (!('speechSynthesis' in window)) { alert('This browser does not support voice preview.'); return; }
    const voice = window.speechSynthesis.getVoices().find(item => item.lang.toLowerCase().startsWith('bn'));
    if (!voice) { alert('No Bangla voice is available in this browser. Text testing is still available.'); return; }
    window.speechSynthesis.cancel();
    const speech = new SpeechSynthesisUtterance(text);
    speech.voice = voice; speech.lang = voice.lang; speech.rate = 1;
    window.speechSynthesis.speak(speech);
};
