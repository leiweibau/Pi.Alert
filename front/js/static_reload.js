function checkPiAlertAvailability() {
  fetch('../../')
    .then(response => {
      if (response.ok) {
        window.location.href = '../../';
      }
    })
    .catch(error => {
      // offline – check again
    });
}
setTimeout(() => {
  checkPiAlertAvailability();
  setInterval(checkPiAlertAvailability, 30000);
}, 270000);