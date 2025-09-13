
document.addEventListener('DOMContentLoaded', function() {
  var toggleButton = document.querySelector('.awp-toggle-sidebar');
  var sidebar = document.querySelector('.awp-sidebar');
  var mainContainer = document.querySelector('.awp-main-container');
  if (toggleButton && sidebar && mainContainer) {
    toggleButton.addEventListener('click', function() {
      sidebar.classList.toggle('active');
      mainContainer.classList.toggle('sidebar-open');
    });
  }
});
