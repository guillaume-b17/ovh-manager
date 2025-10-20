import './styles/app.scss';

// ============================================================
// 🔹 Gestion simple des messages flash (disparition automatique)
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const flashes = document.querySelectorAll('.alert');
    flashes.forEach((flash) => {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.5s';
            flash.style.opacity = 0;
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    });
});

// ============================================================
// 🔹 Petit loader global (facultatif)
// ============================================================
function showLoader(message = 'Chargement...') {
    const loader = document.createElement('div');
    loader.id = 'global-loader';
    loader.innerHTML = `
    <div class="loader-overlay">
      <div class="loader-content">
        <div class="spinner-border text-light" role="status"></div>
        <p>${message}</p>
      </div>
    </div>
  `;
    document.body.appendChild(loader);
}

function hideLoader() {
    const loader = document.getElementById('global-loader');
    if (loader) loader.remove();
}

window.addEventListener('load', () => hideLoader());
