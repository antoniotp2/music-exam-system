document.addEventListener('DOMContentLoaded', function () {
    const timerElement = document.getElementById('timer');
    const form = document.getElementById('examForm');
    const autoSubmittedInput = document.getElementById('auto_submitted');

    if (!timerElement || !form) return;

    let remainingSeconds = parseInt(timerElement.dataset.remainingSeconds, 10) || 0;

    function updateTimer() {
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;

        timerElement.textContent =
            String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

        if (remainingSeconds <= 0) {
            if (autoSubmittedInput) {
                autoSubmittedInput.value = '1';
            }
            form.submit();
            return;
        }

        remainingSeconds--;
    }

    updateTimer();
    setInterval(updateTimer, 1000);
});