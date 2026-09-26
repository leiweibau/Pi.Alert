// Spinner nach 200 Sekunden stoppen (bestehender Shutdown-Countdown).
setTimeout(() => {
  const spinner = document.getElementById('pialert-spinner');
  if (spinner) {
    spinner.style.animationPlayState = 'paused';
  }
}, 200000);
