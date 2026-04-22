document.addEventListener("DOMContentLoaded", function () {
    const timerElement = document.getElementById("timer");
    const examForm = document.getElementById("examForm");

    if (!timerElement || !examForm) return;

    let remaining = Number(timerElement.dataset.remainingSeconds);
    let hasSubmitted = false;

    if (!Number.isFinite(remaining) || remaining < 0) {
        remaining = 0;
    }

    function autoSubmitExam() {
        if (hasSubmitted) return;
        hasSubmitted = true;

        timerElement.textContent = "Time is up! Submitting...";
        
        const hiddenInput = document.createElement("input");
        hiddenInput.type = "hidden";
        hiddenInput.name = "auto_submitted";
        hiddenInput.value = "1";
        examForm.appendChild(hiddenInput);

        examForm.submit();
    }

    function updateTimer() {
        if (remaining <= 0) {
            autoSubmitExam();
            return;
        }

        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;

        timerElement.textContent =
            `Time Left: ${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;

        remaining--;
    }

    updateTimer();
    setInterval(updateTimer, 1000);
});