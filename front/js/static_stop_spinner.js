// Spinner nach 5 Minuten stoppen (300.000 ms)
setTimeout(() => {
  const spinner = document.getElementById('pialert-spinner');
  if (spinner) {
    spinner.style.animationPlayState = 'paused';
  }
}, 300000);
