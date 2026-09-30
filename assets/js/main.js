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

    function formatDate(value) {
        if (!value) return '';
        var d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d)) return String(value);
        return d.toLocaleDateString('es-CO', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    }

    /* ---------- Auto-descartar alertas ---------- */
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (a) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(a);
            bsAlert.close();
        }, 4500);
    });

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
        var year, month; // month: 0-11
        var today = new Date();
        var selectedUserId = null;

        if (weekDaysEl) weekDaysEl.innerHTML = DIAS.map(function (d) { return '<div>' + d + '</div>'; }).join('');

        function usersOnDay(day, mon) {
            return birthdayUsers.filter(function (u) { return u.dia === day && u.mes === mon + 1; });
        }

        function render() {
            titleEl.textContent = MESES[month] + ' ' + year;
            var firstDay = new Date(year, month, 1).getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var html = '';
            var i;

            for (i = 0; i < firstDay; i++) html += '<div class="mm-day empty"></div>';
            for (var d = 1; d <= daysInMonth; d++) {
                var users = usersOnDay(d, month);
                var isToday = d === today.getDate() && month === today.getMonth() && year === today.getFullYear();
                var cls = 'mm-day';
                if (isToday) cls += ' today';
                if (users.length) cls += ' has-birthday';
                if (users.length > 1) cls += ' multi';

                var badge = users.length ? '<span class="bday-count">' + users.length + '</span>' : '';
                var dot = users.length ? '<span class="bday-dot"></span>' : '';
                html += '<div class="' + cls + '" data-day="' + d + '" data-month="' + month + '">'
                    + dot + '<span class="day-num">' + d + '</span>' + badge + '</div>';
            }
            gridEl.innerHTML = html;

            gridEl.querySelectorAll('.mm-day.has-birthday').forEach(function (cell) {
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
            var list = upcoming.concat(later).slice(0, 6);

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
            var users = usersOnDay(day, mon);
            if (!users.length) return;

            modalTitle.textContent = '🎂 Cumpleaños · ' + day + ' de ' + MESES[mon];
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
                            + '<div class="small text-muted mt-1">' + escapeHtml(c.autor) + ' · ' + formatDate(c.fecha_creacion) + '</div></div>'
                            + '</div>';
                    }).join('');
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

        fetch(calCfg.getBirthdaysUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                birthdayUsers = data;
                birthdayUsers.forEach(function (u) { usersById[u.id] = u; });
                year = today.getFullYear();
                month = today.getMonth();
                render();
                renderUpcoming();
            })
            .catch(function () {
                gridEl.innerHTML = '<p class="text-danger text-center py-4">No se pudieron cargar los cumpleaños.</p>';
            });
    }

    /* =====================================================
       NOTICIAS - comentarios por carga diferida
       ===================================================== */
    function renderNewsComments(listEl, comments) {
        if (!comments.length) {
            listEl.innerHTML = '<p class="text-muted small mb-0">Sé el primero en comentar. 💬</p>';
            return;
        }
        listEl.innerHTML = comments.map(function (c) {
            return '<div class="mm-comment">'
                + '<img src="' + c.foto_url + '" alt="">'
                + '<div><div class="bubble">' + escapeHtml(c.comentario) + '</div>'
                + '<div class="small text-muted mt-1">' + escapeHtml(c.autor) + ' · ' + formatDate(c.fecha_creacion) + '</div></div>'
                + '</div>';
        }).join('');
    }

    var newsCfg = window.MM_NEWS_CONFIG;
    if (newsCfg) {
        document.querySelectorAll('.js-news-comments-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var noticiaId = btn.dataset.noticiaId;
                var panel = document.getElementById('newsComments-' + noticiaId);
                if (!panel) return;

                if (panel.classList.contains('loaded')) {
                    panel.classList.toggle('d-none');
                    return;
                }
                panel.classList.remove('d-none');
                panel.classList.add('loaded');

                var listEl = panel.querySelector('.js-comments-list');
                var form = panel.querySelector('.js-comment-form');
                var url = newsCfg.getCommentsUrl + '?noticia_id=' + noticiaId;

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (comments) { renderNewsComments(listEl, comments); });

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
                            if (data.success) {
                                input.value = '';
                                return fetch(url, { headers: { 'Accept': 'application/json' } })
                                    .then(function (r) { return r.json(); })
                                    .then(function (comments) { renderNewsComments(listEl, comments); });
                            }
                            alert(data.error || 'No se pudo enviar el comentario.');
                        });
                });
            });
        });

        // Mostrar/ocultar campos según el tipo de publicación (solo superadmin)
        window.toggleNewsTypeFields = function (tipo) {
            document.querySelectorAll('.news-field').forEach(function (f) {
                f.classList.add('d-none');
            });
            document.querySelectorAll('.news-field[data-type="' + tipo + '"]').forEach(function (f) {
                f.classList.remove('d-none');
            });
        };
    }

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
