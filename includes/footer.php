    <!-- ============ Pie de página ============ -->
    <footer class="mm-footer py-4 mt-auto">
        <div class="container">
            <div class="row align-items-center g-3">
                <div class="col-md-5 text-center text-md-start">
                    <img src="<?= asset('img/logo.png') ?>" alt="monchomania" class="mm-footer-logo">
                </div>
                <div class="col-md-7 text-center">
                    <p class="mb-1 text-white">Hecho con ❤️ para la comunidad</p>
                    <p class="mb-0 text-white small">
                        Creado por <strong>Jorge Andrés Bonett Navarro</strong> · Ingeniero de Software
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- ============ Ayuda para descargar la app ============ -->
    <div class="modal fade" id="installHelpModal" tabindex="-1" aria-labelledby="installHelpTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header mm-modal-header">
                    <h5 class="modal-title" id="installHelpTitle">📲 Descargar monchomania como app</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Instálala en tu celular o computador y ábrela como una app, sin el navegador.</p>
                    <ul class="mb-0 small">
                        <li class="mb-2">
                            <strong>Android (Chrome):</strong> abre el menú ⋮ y elige
                            «Instalar aplicación» o «Añadir a pantalla de inicio».
                        </li>
                        <li class="mb-2">
                            <strong>iPhone / iPad (Safari):</strong> toca Compartir ⬆️ y luego
                            «Añadir a pantalla de inicio».
                        </li>
                        <li>
                            <strong>Computador (Chrome / Edge):</strong> usa el icono de instalar
                            (⊕ o pantalla con flecha) que aparece en la barra de direcciones.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
    window.MM_APP = {
        swUrl:   '<?= base_url('sw.js') ?>',
        scope:   '<?= base_url('/') ?>',
        baseUrl: '<?= base_url() ?>'
    };
    </script>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JavaScript propio -->
    <script src="<?= asset_versioned('js/main.js') ?>"></script>
    <script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js')
      .then(() => console.log("Service Worker registrado con éxito"))
      .catch((error) => console.log("Fallo al registrar el Service Worker", error));
  }
</script>
</body>
</html>
