/* =====================================================
   monchomania - JavaScript principal
   Calendario, comentarios, noticias y utilidades.
   ===================================================== */
(function () {
    'use strict';

    /* ---------- Utilidades ---------- */
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- Fechas y horas en la zona del dispositivo del usuario ---------- */
    function parseFecha(value) {
        if (!value) return null;

        var texto = String(value).trim();

        // Fecha sin hora (aaaa-mm-dd): se conserva el día, sin convertir zona horaria.
        if (/^\d{4}-\d{2}-\d{2}$/.test(texto)) {
            var partes = texto.split('-');
            return new Date(parseInt(partes[0], 10), parseInt(partes[1], 10) - 1, parseInt(partes[2], 10));
        }

        texto = texto.replace(' ', 'T');
        if (texto.indexOf('Z') === -1 && texto.indexOf('+') === -1) {
            texto += 'Z'; // las fechas de la base de datos están en UTC
        }

        var d = new Date(texto);
        return isNaN(d) ? null : d;
    }

    function formatFechaHora(value) {
        var d = parseFecha(value);
        if (!d) return value == null ? '' : String(value);

        if (/^\d{4}-\d{2}-\d{2}$/.test(String(value).trim())) {
            return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }
        // La zona horaria es la del dispositivo; el formato de fecha es latino (dd/mm/aaaa).
        return d.toLocaleString('es-CO', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: true
        });
    }

    function formatHora(value) {
        if (!value) return '';

        var partes = String(value).split(':');
        var horas = parseInt(partes[0], 10);
        var minutos = parseInt(partes[1], 10);
        if (isNaN(horas) || isNaN(minutos)) return String(value);

        var d = new Date();
        d.setHours(horas, minutos, 0, 0);
        return d.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function formatDate(value) {
        var d = parseFecha(value);
        if (!d) return value == null ? '' : String(value);

        return d.toLocaleString('es-CO', {
            day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', hour12: true
        });
    }

    // Reemplaza los textos de respaldo del servidor por la hora local del dispositivo
    document.querySelectorAll('time[datetime]').forEach(function (el) {
        var valor = el.getAttribute('datetime');
        if (!valor) return;
        el.textContent = /^\d{1,2}:\d{2}/.test(valor) ? formatHora(valor) : formatFechaHora(valor);
    });

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /* ---------- Botón para que el superadmin borre un comentario ---------- */
    function commentDeleteButton(commentId) {
        var cfg = window.MM_COMMENT_DELETE;
        if (!cfg || !cfg.isAdmin || !cfg.url) return '';

        return '<button type="button" class="btn btn-sm btn-outline-danger js-comment-delete"'
            + ' data-comment-id="' + commentId + '" data-delete-url="' + cfg.url + '"'
            + ' title="Eliminar comentario">🗑️</button>';
    }

    function initCommentDelete(scope) {
        (scope || document).querySelectorAll('.js-comment-delete').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';

            btn.addEventListener('click', function () {
                if (!confirm('¿Eliminar este comentario definitivamente?')) return;
                btn.disabled = true;

                fetch(btn.dataset.deleteUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken()
                    },
                    body: JSON.stringify({ comment_id: parseInt(btn.dataset.commentId, 10) })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            alert(data.error || 'No se pudo eliminar el comentario.');
                            btn.disabled = false;
                            return;
                        }

                        var item = btn.closest('.mm-comment');
                        if (item) item.remove();

                        var listEl = document.querySelector('.js-comments-list');
                        if (listEl && !listEl.querySelector('.mm-comment')) {
                            listEl.innerHTML = '<p class="text-muted small mb-0">Todavía no hay comentarios. 💬</p>';
                        }

                        var counter = document.querySelector('.js-comments-count');
                        if (counter && listEl) {
                            counter.textContent = listEl.querySelectorAll('.mm-comment').length;
                        }
                    })
                    .catch(function () {
                        alert('No se pudo eliminar el comentario.');
                        btn.disabled = false;
                    });
            });
        });
    }

    /* ---------- Auto-descartar alertas ---------- */
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (a) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(a);
            bsAlert.close();
        }, 4500);
    });

    // Botones de borrado ya renderizados por el servidor (mis saludos)
    initCommentDelete(document);

    /* ---------- Campanita de notificaciones ---------- */
    var bell = document.querySelector('.mm-bell[data-read-url]');
    if (bell) {
        var notifBadge = document.querySelector('.js-notif-badge');

        function actualizarBadge(total) {
            if (!notifBadge) return;
            notifBadge.textContent = total;
            notifBadge.classList.toggle('d-none', !total);
        }

        function borrarNotificacion(datos, alTerminar) {
            return fetch(bell.dataset.deleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken()
                },
                body: JSON.stringify(datos)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success) return;
                    actualizarBadge(data.sinLeer);
                    if (alTerminar) alTerminar();
                })
                .catch(function () { /* silencioso */ });
        }

        // Al abrirla se marcan como vistas: el aviso desaparece.
        bell.addEventListener('click', function () {
            actualizarBadge(0);

            fetch(bell.dataset.readUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken()
                },
                body: '{}'
            }).catch(function () { /* silencioso */ });
        });

        // La ✕ quita una notificación para que la lista no se llene.
        document.querySelectorAll('.js-notif-dismiss').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var fila = btn.closest('.mm-notif-row');
                borrarNotificacion({ id: parseInt(btn.dataset.notifId, 10) }, function () {
                    if (fila) fila.remove();
                });
            });
        });

        document.querySelectorAll('.js-notif-dismiss-all').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                borrarNotificacion({ all: true }, function () {
                    document.querySelectorAll('.mm-notif-row').forEach(function (fila) { fila.remove(); });
                    var vacio = document.querySelector('.js-notif-empty');
                    if (vacio) vacio.classList.remove('d-none');
                    btn.classList.add('d-none');
                });
            });
        });
    }

    /* =====================================================
       APP INSTALABLE (PWA): botones "Descargar como app"
       ===================================================== */
    var installButtons = document.querySelectorAll('.js-install-app');
    var deferredInstall = null;
    var yaInstalada = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    if (yaInstalada) {
        installButtons.forEach(function (btn) { btn.classList.add('d-none'); });
    }

    // Chrome/Edge/Android avisan cuando la app se puede instalar
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredInstall = e;
    });

    window.addEventListener('appinstalled', function () {
        deferredInstall = null;
        installButtons.forEach(function (btn) { btn.classList.add('d-none'); });
    });

    installButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();

            if (deferredInstall) {
                deferredInstall.prompt();
                deferredInstall.userChoice.then(function () { deferredInstall = null; });
                return;
            }

            // Sin instalación automática (iPhone/Safari, navegador ya instalado…):
            // se explican los pasos manuales.
            var modalEl = document.getElementById('installHelpModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });

    if ('serviceWorker' in navigator && window.MM_APP && window.MM_APP.swUrl) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(window.MM_APP.swUrl, { scope: window.MM_APP.scope })
                .catch(function () { /* sin service worker la web funciona igual */ });
        });
    }

    /* ---------- Vista previa de foto al seleccionar archivo ---------- */
    document.querySelectorAll('.photo-input').forEach(function (input) {
        input.addEventListener('change', function () {
            var selector = input.dataset.preview;
            if (!selector) return;
            var img = document.querySelector(selector);
            var wrap = document.getElementById(selector.replace('#', '') + 'Wrap');
            if (img && input.files && input.files[0]) {
                img.src = URL.createObjectURL(input.files[0]);
                if (wrap) wrap.classList.remove('d-none');
            }
        });
    });

    /* ---------- Mostrar / ocultar contraseña (ojito) ---------- */
    var EYE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">' +
        '<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8a13.133 13.133 0 0 1-1.66 2.043C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13.133 13.133 0 0 1 1.172 8z"/>' +
        '<path d="M8 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6m0 1a2 2 0 1 1 0 4 2 2 0 0 1 0-4"/></svg>';
    var EYE_SLASH_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">' +
        '<path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486z"/>' +
        '<path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>' +
        '<path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/></svg>';

    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        var input = btn.dataset.target
            ? document.querySelector(btn.dataset.target)
            : btn.parentElement.querySelector('input');
        if (!input) return;

        var setIcon = function () {
            btn.innerHTML = input.type === 'password' ? EYE_ICON : EYE_SLASH_ICON;
        };
        setIcon();

        btn.addEventListener('click', function () {
            input.type = input.type === 'password' ? 'text' : 'password';
            setIcon();
            input.focus();
        });
    });

    /* =====================================================
       CALENDARIO DE CUMPLEAÑOS
       ===================================================== */
    var calCfg = window.MM_CALENDAR_CONFIG;
    if (calCfg) {
        var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        var DIAS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        var titleEl = document.getElementById('calendarTitle');
        var gridEl = document.getElementById('calendarGrid');
        var weekDaysEl = document.getElementById('weekDays');
        var prevBtn = document.getElementById('prevMonth');
        var nextBtn = document.getElementById('nextMonth');
        var todayBtn = document.getElementById('todayBtn');
        var upcomingEl = document.getElementById('upcomingBirthdays');

        var modalEl = document.getElementById('birthdayModal');
        var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        var peopleEl = document.getElementById('birthdayPeople');
        var commentsSection = document.getElementById('commentsSection');
        var commentsDivider = document.getElementById('commentsDivider');
        var commentsList = document.getElementById('commentsList');
        var selectedName = document.getElementById('selectedPersonName');
        var commentForm = document.getElementById('commentForm');
        var commentUserId = document.getElementById('commentUserId');
        var commentText = document.getElementById('commentText');
        var modalTitle = document.getElementById('birthdayModalTitle');

        var birthdayUsers = [];
        var usersById = {};
        var eventos = [];
        var eventosPorFecha = {};
        var today = new Date();
        var year = today.getFullYear();
        var month = today.getMonth(); // 0-11
        var selectedUserId = null;

        var dayEventsEl = document.getElementById('dayEvents');
        var dayEventsListEl = document.getElementById('dayEventsList');
        var upcomingEventsEl = document.getElementById('upcomingEvents');
        var filtroCumple = document.getElementById('filtroCumple');
        var filtroEventos = document.getElementById('filtroEventos');

        // Filtros del calendario: por defecto se ven cumpleaños y eventos
        function verCumples() {
            return !filtroCumple || filtroCumple.checked;
        }

        function verEventos() {
            return !filtroEventos || filtroEventos.checked;
        }

        if (weekDaysEl) weekDaysEl.innerHTML = DIAS.map(function (d) { return '<div>' + d + '</div>'; }).join('');

        function usersOnDay(day, mon) {
            return birthdayUsers.filter(function (u) { return u.dia === day && u.mes === mon + 1; });
        }

        function eventsOnDay(day, mon) {
            return eventosPorFecha[year + '-' + (mon + 1) + '-' + day] || [];
        }

        function render() {
            titleEl.textContent = MESES[month] + ' ' + year;
            var firstDay = new Date(year, month, 1).getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var html = '';
            var i;

            for (i = 0; i < firstDay; i++) html += '<div class="mm-day empty"></div>';
            for (var d = 1; d <= daysInMonth; d++) {
                var users = verCumples() ? usersOnDay(d, month) : [];
                var evs = verEventos() ? eventsOnDay(d, month) : [];
                var isToday = d === today.getDate() && month === today.getMonth() && year === today.getFullYear();
                var cls = 'mm-day';
                if (isToday) cls += ' today';
                if (users.length) cls += ' has-birthday';
                if (evs.length) cls += ' has-event';
                if (users.length > 1) cls += ' multi';

                var badge = users.length ? '<span class="bday-count">' + users.length + '</span>' : '';
                var dot = users.length ? '<span class="bday-dot"></span>' : '';
                var eventDot = evs.length ? '<span class="event-dot"></span>' : '';
                html += '<div class="' + cls + '" data-day="' + d + '" data-month="' + month + '"'
                    + (evs.length ? ' title="' + escapeHtml(evs[0].titulo) + '"' : '') + '>'
                    + dot + eventDot + '<span class="day-num">' + d + '</span>' + badge + '</div>';
            }
            gridEl.innerHTML = html;

            gridEl.querySelectorAll('.mm-day.has-birthday, .mm-day.has-event').forEach(function (cell) {
                cell.addEventListener('click', function () {
                    openDayModal(parseInt(cell.dataset.day, 10), parseInt(cell.dataset.month, 10));
                });
            });
        }

        function renderUpcoming() {
            var now = { m: today.getMonth() + 1, d: today.getDate() };
            var sorted = birthdayUsers.slice().sort(function (a, b) {
                return (a.mes - b.mes) || (a.dia - b.dia);
            });
            var upcoming = sorted.filter(function (u) {
                return (u.mes > now.m) || (u.mes === now.m && u.dia >= now.d);
            });
            var later = sorted.filter(function (u) {
                return (u.mes < now.m) || (u.mes === now.m && u.dia < now.d);
            });
            var list = upcoming.concat(later).slice(0, 10);

            if (!list.length) {
                upcomingEl.innerHTML = '<p class="text-muted small mb-0">No hay cumpleaños registrados.</p>';
                return;
            }
            upcomingEl.innerHTML = list.map(function (u) {
                var fecha = ('0' + u.dia).slice(-2) + '/' + ('0' + u.mes).slice(-2);
                return '<div class="d-flex align-items-center gap-2 mb-2">'
                    + '<img src="' + u.foto_url + '" class="rounded-circle" width="36" height="36" style="object-fit:cover" alt="">'
                    + '<div class="small"><strong>' + escapeHtml(u.nombre_completo) + '</strong><br>'
                    + '<span class="text-muted">' + fecha + '</span></div></div>';
            }).join('');
        }

        function openDayModal(day, mon) {
            var users = verCumples() ? usersOnDay(day, mon) : [];
            var evs = verEventos() ? eventsOnDay(day, mon) : [];
            if (!users.length && !evs.length) return;

            modalTitle.textContent = '📅 ' + day + ' de ' + MESES[mon];

            if (dayEventsEl) {
                dayEventsEl.classList.toggle('d-none', !evs.length);
                dayEventsListEl.innerHTML = evs.map(function (ev) {
                    return '<div class="mm-event-card mb-2">'
                        + '<div class="fw-semibold">📌 ' + escapeHtml(ev.titulo) + '</div>'
                        + (ev.hora ? '<div class="small text-muted">🕒 ' + escapeHtml(formatHora(ev.hora)) + '</div>' : '')
                        + (ev.lugar ? '<div class="small text-muted">📍 ' + escapeHtml(ev.lugar) + '</div>' : '')
                        + (ev.descripcion ? '<div class="small mt-1">' + escapeHtml(ev.descripcion) + '</div>' : '')
                        + '</div>';
                }).join('');
            }

            peopleEl.innerHTML = users.map(function (u) {
                return '<div class="col-md-6">'
                    + '<div class="mm-person-card">'
                    + '<img src="' + u.foto_url + '" alt="' + escapeHtml(u.nombre_completo) + '">'
                    + '<div>'
                    + '<h6 class="mb-1">' + escapeHtml(u.nombre_completo) + '</h6>'
                    + '<p class="text-muted small mb-1">📍 ' + escapeHtml(u.direccion || 'Sin dirección')
                    + (u.barrio ? ' · ' + escapeHtml(u.barrio) : '') + '</p>'
                    + '<span class="badge mm-zone">' + escapeHtml(u.zona) + '</span> '
                    + '<button class="btn btn-sm btn-danger mt-2" data-user-id="' + u.id + '">💬 Felicitar</button>'
                    + '</div></div></div>';
            }).join('');

            commentsSection.classList.add('d-none');
            commentsDivider.classList.add('d-none');
            selectedUserId = null;

            peopleEl.querySelectorAll('button[data-user-id]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var u = usersById[parseInt(btn.dataset.userId, 10)];
                    if (u) selectUser(u);
                });
            });

            if (modal) modal.show();
        }

        function selectUser(u) {
            selectedUserId = u.id;
            selectedName.textContent = u.nombre_completo;
            commentUserId.value = u.id;
            commentsSection.classList.remove('d-none');
            commentsDivider.classList.remove('d-none');
            loadComments(u.id);
            if (commentText) commentText.focus();
        }

        function loadComments(id) {
            commentsList.innerHTML = '<p class="text-muted small mb-0">Cargando saludos…</p>';
            fetch(calCfg.getCommentsUrl + '?usuario_id=' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (comments) {
                    if (!comments.length) {
                        commentsList.innerHTML = '<p class="text-muted small mb-0">Aún no hay saludos. ¡Sé el primero en felicitar! 🎉</p>';
                        return;
                    }
                    commentsList.innerHTML = comments.map(function (c) {
                        return '<div class="mm-comment">'
                            + '<img src="' + c.foto_url + '" alt="">'
                            + '<div><div class="bubble">' + escapeHtml(c.comentario) + '</div>'
                            + '<div class="small text-muted mt-1 d-flex align-items-center gap-2">'
                            + '<span>' + escapeHtml(c.autor) + ' · ' + formatDate(c.fecha_creacion) + '</span>'
                            + commentDeleteButton(c.id)
                            + '</div></div>'
                            + '</div>';
                    }).join('');

                    initCommentDelete(commentsList);
                })
                .catch(function () {
                    commentsList.innerHTML = '<p class="text-danger small mb-0">No se pudieron cargar los saludos.</p>';
                });
        }

        if (commentForm) {
            commentForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var text = commentText.value.trim();
                if (!text || !selectedUserId) return;

                fetch(calCfg.commentUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': calCfg.csrfToken
                    },
                    body: JSON.stringify({ usuario_id: selectedUserId, comentario: text })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            commentText.value = '';
                            loadComments(selectedUserId);
                        } else {
                            alert(data.error || 'No se pudo enviar el comentario.');
                        }
                    })
                    .catch(function () { alert('Error de conexión al enviar el saludo.'); });
            });
        }

        prevBtn.addEventListener('click', function () {
            month--;
            if (month < 0) { month = 11; year--; }
            render();
        });
        nextBtn.addEventListener('click', function () {
            month++;
            if (month > 11) { month = 0; year++; }
            render();
        });
        todayBtn.addEventListener('click', function () {
            year = today.getFullYear();
            month = today.getMonth();
            render();
        });

        function renderUpcomingEvents() {
            if (!upcomingEventsEl) return;

            var hoy = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2) + '-' + ('0' + today.getDate()).slice(-2);
            var proximos = eventos.filter(function (ev) { return ev.fecha >= hoy; }).slice(0, 10);

            if (!proximos.length) {
                upcomingEventsEl.innerHTML = '<p class="text-muted small mb-0">No hay eventos programados.</p>';
                return;
            }

            upcomingEventsEl.innerHTML = proximos.map(function (ev) {
                var partes = String(ev.fecha).split('-');
                var fecha = partes[2] + '/' + partes[1] + '/' + partes[0];
                return '<div class="mb-2">'
                    + '<div class="small fw-semibold">📌 ' + escapeHtml(ev.titulo) + '</div>'
                    + '<div class="small text-muted">' + fecha + (ev.hora ? ' · ' + escapeHtml(formatHora(ev.hora)) : '') + '</div>'
                    + (ev.lugar ? '<div class="small text-muted">📍 ' + escapeHtml(ev.lugar) + '</div>' : '')
                    + '</div>';
            }).join('');
        }

        // Abre el mes y el día de una fecha concreta (enlace desde una notificación)
        function abrirFechaDestacada(fecha) {
            var partes = String(fecha).split('-');
            var fAnio = parseInt(partes[0], 10);
            var fMes = parseInt(partes[1], 10) - 1;
            var fDia = parseInt(partes[2], 10);

            if (isNaN(fAnio) || isNaN(fMes) || isNaN(fDia)) return;

            year = fAnio;
            month = fMes;
            render();
            openDayModal(fDia, fMes);
        }

        var cargas = [
            fetch(calCfg.getBirthdaysUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    birthdayUsers = data;
                    birthdayUsers.forEach(function (u) { usersById[u.id] = u; });
                })
                .catch(function () {
                    gridEl.innerHTML = '<p class="text-danger text-center py-4">No se pudieron cargar los cumpleaños.</p>';
                })
        ];

        if (calCfg.getEventsUrl) {
            cargas.push(
                fetch(calCfg.getEventsUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        eventos = data;
                        eventosPorFecha = {};
                        eventos.forEach(function (ev) {
                            var key = ev.anio + '-' + ev.mes + '-' + ev.dia;
                            if (!eventosPorFecha[key]) eventosPorFecha[key] = [];
                            eventosPorFecha[key].push(ev);
                        });
                    })
                    .catch(function () { /* el calendario funciona igual sin eventos */ })
            );
        }

        // Filtros: ocultan del calendario y de la barra lateral lo que no se quiera ver
        function aplicarFiltros() {
            var cardCumple = document.getElementById('upcomingBirthdays');
            if (cardCumple) cardCumple.closest('.card').classList.toggle('d-none', !verCumples());

            var cardEventos = document.getElementById('upcomingEvents');
            if (cardEventos) cardEventos.closest('.card').classList.toggle('d-none', !verEventos());

            render();
        }

        [filtroCumple, filtroEventos].forEach(function (chk) {
            if (chk) chk.addEventListener('change', aplicarFiltros);
        });

        Promise.all(cargas).then(function () {
            render();
            renderUpcoming();
            renderUpcomingEvents();

            if (calCfg.fechaDestacada) {
                abrirFechaDestacada(calCfg.fechaDestacada);
            }
        });
    }

    /* =====================================================
       NOTICIAS - comentarios y "me gusta" (vista de detalle)
       ===================================================== */
    function renderNewsComments(listEl, comments) {
        var counter = document.querySelector('.js-comments-count');
        if (counter) counter.textContent = comments.length;

        if (!comments.length) {
            listEl.innerHTML = '<p class="text-muted small mb-0">Sé el primero en comentar. 💬</p>';
            return;
        }
        listEl.innerHTML = comments.map(function (c) {
            return '<div class="mm-comment">'
                + '<img src="' + c.foto_url + '" alt="">'
                + '<div><div class="bubble">' + escapeHtml(c.comentario) + '</div>'
                + '<div class="small text-muted mt-1 d-flex align-items-center gap-2">'
                + '<span>' + escapeHtml(c.autor) + ' · ' + formatDate(c.fecha_creacion) + '</span>'
                + commentDeleteButton(c.id)
                + '</div></div>'
                + '</div>';
        }).join('');

        initCommentDelete(listEl);
    }

    var newsCfg = window.MM_NEWS_CONFIG;
    if (newsCfg) {
        // Comentarios de una publicación (se cargan al abrir el detalle)
        document.querySelectorAll('.js-news-comments').forEach(function (box) {
            var noticiaId = box.dataset.noticiaId;
            var listEl = box.querySelector('.js-comments-list');
            var form = box.querySelector('.js-comment-form');
            if (!noticiaId || !listEl || !form) return;

            var url = newsCfg.getCommentsUrl + '?noticia_id=' + noticiaId;

            function loadComments() {
                return fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (comments) { renderNewsComments(listEl, comments); })
                    .catch(function () {
                        listEl.innerHTML = '<p class="text-danger small mb-0">No se pudieron cargar los comentarios.</p>';
                    });
            }

            loadComments();

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var input = form.querySelector('textarea');
                var text = input.value.trim();
                if (!text) return;

                fetch(newsCfg.commentUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': newsCfg.csrfToken
                    },
                    body: JSON.stringify({ noticia_id: parseInt(noticiaId, 10), comentario: text })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            alert(data.error || 'No se pudo enviar el comentario.');
                            return;
                        }
                        input.value = '';
                        loadComments();
                    })
                    .catch(function () { alert('No se pudo enviar el comentario.'); });
            });
        });

        // Dar o quitar "me gusta" en la vista de detalle
        document.querySelectorAll('.js-news-like').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.disabled = true;

                fetch(newsCfg.likeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': newsCfg.csrfToken
                    },
                    body: JSON.stringify({ noticia_id: parseInt(btn.dataset.noticiaId, 10) })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            alert(data.error || 'No se pudo registrar tu me gusta.');
                            return;
                        }
                        btn.classList.toggle('active', !!data.liked);
                        btn.classList.toggle('btn-primary', !!data.liked);
                        btn.classList.toggle('btn-outline-primary', !data.liked);
                        btn.setAttribute('aria-pressed', data.liked ? 'true' : 'false');

                        var count = btn.querySelector('.js-like-count');
                        if (count) count.textContent = data.total;

                        var summary = document.querySelector('.js-like-summary');
                        if (summary) summary.textContent = data.resumen;
                    })
                    .catch(function () { alert('No se pudo registrar tu me gusta.'); })
                    .then(function () { btn.disabled = false; });
            });
        });

    }

    /* ---------- Formulario de publicación (solo superadmin) ---------- */
    // Mostrar/ocultar campos según el tipo de publicación
    window.toggleNewsTypeFields = function (tipo) {
        document.querySelectorAll('.news-field').forEach(function (f) {
            f.classList.add('d-none');
        });
        document.querySelectorAll('.news-field[data-type="' + tipo + '"]').forEach(function (f) {
            f.classList.remove('d-none');
        });
    };

    // Origen del video: subir desde el equipo o compartir un enlace
    window.toggleVideoSource = function (origen) {
        document.querySelectorAll('.js-video-source').forEach(function (el) {
            el.classList.add('d-none');
        });
        var target = document.getElementById(origen === 'enlace' ? 'videoSourceEnlace' : 'videoSourceSubir');
        if (target) target.classList.remove('d-none');
    };

    /* =====================================================
       ADMIN - editar usuario y activar/inactivar
       ===================================================== */
    window.openEditUser = function (btn) {
        var u = JSON.parse(btn.dataset.user);
        var set = function (id, value) {
            var el = document.getElementById(id);
            if (el) el.value = value == null ? '' : value;
        };
        set('editUserId', u.id);
        set('editUsuario', u.usuario);
        set('editNombre', u.nombre);
        set('editApellidos', u.apellidos);
        set('editFecha', u.fecha_nacimiento);
        set('editEmail', u.email);
        set('editDireccion', u.direccion);
        set('editBarrio', u.barrio);
        set('editZona', u.zona);
        set('editRol', u.id_rol);
        set('editEstado', u.estado);

        var modalEl = document.getElementById('editUserModal');
        if (modalEl) new bootstrap.Modal(modalEl).show();
    };

    window.toggleUser = function (id, estado) {
        var mensaje = estado === 1 ? 'activar' : 'inactivar';
        if (!confirm('¿Seguro que deseas ' + mensaje + ' este usuario?')) return;

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        form.innerHTML =
            '<input type="hidden" name="action" value="toggle">' +
            '<input type="hidden" name="id" value="' + id + '">' +
            '<input type="hidden" name="estado" value="' + estado + '">' +
            '<input type="hidden" name="csrf_token" value="' + (window.MM_ADMIN_CSRF || '') + '">';
        document.body.appendChild(form);
        form.submit();
    };
})();
