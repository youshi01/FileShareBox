(function () {
    // 前台主脚本：统一管理首页取件、上传页交互、提取码弹窗、toast 和后台删除确认。
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function bytesToSize(bytes) {
        if (!bytes) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
    }

    function each(list, callback) {
        Array.prototype.forEach.call(list || [], callback);
    }

    function showResult(el, ok, html) {
        if (!el) return;
        el.className = 'result ' + (ok ? 'ok' : 'err');
        el.innerHTML = html;
    }

    function uploadLimitMessage() {
        return '文件过大，已超过当前服务器运行时上传上限，请检查并重启 Nginx 或 PHP。';
    }

    // 统一解析接口响应：优先兼容 JSON，必要时根据 HTTP 状态码回退到更友好的文案。
    async function readApiResponse(response) {
        var text = '';

        try {
            text = await response.text();
        } catch (err) {
            return { ok: false, message: '无法读取服务器响应。', __status: response ? response.status : 0 };
        }

        if (!text) {
            if (response.status === 413) {
                return { ok: false, message: uploadLimitMessage(), __status: 413 };
            }

            return {
                ok: response.ok,
                message: response.ok ? '' : ('请求失败（HTTP ' + response.status + '）。'),
                __status: response.status
            };
        }

        try {
            var data = JSON.parse(text);
            if (data && typeof data === 'object') {
                data.__status = response.status;
                return data;
            }
        } catch (err) {
            // Fall through to HTTP-aware fallback handling.
        }

        if (response.status === 413) {
            return { ok: false, message: uploadLimitMessage(), __status: 413 };
        }

        return {
            ok: false,
            message: '服务器返回了无法解析的响应（HTTP ' + response.status + '）。',
            __status: response.status
        };
    }

    async function submitForm(form) {
        var formData = new FormData(form);
        var response = await fetch(form.action, {
            method: form.method || 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        return await readApiResponse(response);
    }

    function setSingleFile(input, file) {
        if (!input || !file) return;
        if (typeof DataTransfer !== 'undefined') {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        }
    }

    function setZoneText(zone, titleText, tipText) {
        if (!zone) return;
        var title = zone.querySelector('strong');
        var tip = zone.querySelector('span');
        if (title) title.textContent = titleText;
        if (tip) tip.textContent = tipText;
    }

    function createToastStack() {
        var existing = document.getElementById('toastStack');
        if (existing) return existing;

        var stack = document.createElement('div');
        stack.id = 'toastStack';
        stack.className = 'toast-stack';
        stack.setAttribute('aria-live', 'polite');
        stack.setAttribute('aria-atomic', 'true');
        document.body.appendChild(stack);
        return stack;
    }

    var toastStack = createToastStack();
    // Keep the page in lightweight mode by default. The previous visual effects
    // looked good but were too expensive on some Windows machines and browsers.
    var lowPower = true;

    document.body.classList.add('perf-lite');

    var cursorGlow = document.getElementById('cursorGlow');
    if (cursorGlow && !lowPower && window.matchMedia && window.matchMedia('(pointer:fine)').matches) {
        var glowX = -120;
        var glowY = -120;
        var glowActive = false;
        var glowRaf = 0;

        function paintGlow() {
            glowRaf = 0;
            cursorGlow.style.transform = 'translate(' + glowX + 'px, ' + glowY + 'px)';
            cursorGlow.style.opacity = glowActive ? '0.55' : '0';
        }

        function queueGlowPaint() {
            if (glowRaf) return;
            glowRaf = requestAnimationFrame(paintGlow);
        }

        document.addEventListener('mousemove', function (event) {
            glowActive = true;
            glowX = event.clientX - 120;
            glowY = event.clientY - 120;
            queueGlowPaint();
        }, { passive: true });

        document.addEventListener('mouseleave', function () {
            glowActive = false;
            queueGlowPaint();
        });
    }

    function showToast(message, type) {
        if (!toastStack || !message) return;

        var toast = document.createElement('div');
        toast.className = 'toast toast-' + (type || 'info');
        toast.textContent = String(message);
        toastStack.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('is-show');
        });

        setTimeout(function () {
            toast.classList.remove('is-show');
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 180);
        }, 2400);
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            try {
                var input = document.createElement('textarea');
                input.value = text;
                input.setAttribute('readonly', 'readonly');
                input.style.position = 'absolute';
                input.style.left = '-9999px';
                document.body.appendChild(input);
                input.select();
                var ok = document.execCommand('copy');
                document.body.removeChild(input);
                if (ok) {
                    resolve();
                } else {
                    reject(new Error('copy failed'));
                }
            } catch (err) {
                reject(err);
            }
        });
    }

    // 为 tab 容器注入一个滑动指示器，让切换态由 JS 动态对齐到当前激活按钮。
    function initTabIndicator(container, className) {
        if (!container) return null;
        var indicator = document.createElement('span');
        indicator.className = className;
        indicator.setAttribute('aria-hidden', 'true');
        container.appendChild(indicator);
        return indicator;
    }

    function moveTabIndicator(container, indicator, activeSelector) {
        if (!container || !indicator) return;

        var active = container.querySelector(activeSelector);
        if (!active) {
            indicator.style.opacity = '0';
            return;
        }

        var box = container.getBoundingClientRect();
        var target = active.getBoundingClientRect();
        var x = Math.round(target.left - box.left);
        var y = Math.round(target.top - box.top);

        indicator.style.width = Math.round(target.width) + 'px';
        indicator.style.height = Math.round(target.height) + 'px';
        indicator.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
        indicator.style.opacity = '1';
    }

    var codeModal = document.getElementById('codeModal');
    var codeModalTitle = document.getElementById('codeModalTitle');
    var codeModalBody = document.getElementById('codeModalBody');
    var modalHideTimer = null;

    function burstModalSparkles() {
        if (!codeModal || lowPower) return;
        var card = codeModal.querySelector('.modal-card');
        if (!card) return;

        var colors = ['#23a4ba', '#2da65c', '#f0a12a', '#ffffff'];
        var centerX = card.clientWidth * 0.5;
        var centerY = 54;
        var count = 12;

        for (var i = 0; i < count; i++) {
            var spark = document.createElement('span');
            spark.className = 'modal-spark';
            var angle = (Math.PI * 2 * i) / count;
            var radius = 54 + Math.random() * 42;
            var sx = Math.cos(angle) * radius;
            var sy = Math.sin(angle) * radius - 30;
            spark.style.left = centerX + 'px';
            spark.style.top = centerY + 'px';
            spark.style.setProperty('--sx', Math.round(sx) + 'px');
            spark.style.setProperty('--sy', Math.round(sy) + 'px');
            spark.style.background = colors[i % colors.length];
            card.appendChild(spark);
            spark.addEventListener('animationend', function () {
                if (this.parentNode) {
                    this.parentNode.removeChild(this);
                }
            });
        }
    }

    function openCodeModal(title, bodyHtml) {
        if (!codeModal) return;
        if (codeModalTitle) codeModalTitle.textContent = title;
        if (codeModalBody) codeModalBody.innerHTML = bodyHtml;

        if (modalHideTimer) {
            clearTimeout(modalHideTimer);
            modalHideTimer = null;
        }

        codeModal.hidden = false;
        requestAnimationFrame(function () {
            codeModal.classList.add('is-visible');
        });
        setTimeout(burstModalSparkles, 40);
        document.body.classList.add('modal-open');
    }

    function closeCodeModal() {
        if (!codeModal || codeModal.hidden) return;
        codeModal.classList.remove('is-visible');
        document.body.classList.remove('modal-open');

        if (modalHideTimer) {
            clearTimeout(modalHideTimer);
        }
        modalHideTimer = setTimeout(function () {
            codeModal.hidden = true;
            modalHideTimer = null;
        }, 180);
    }

    function codeLineHtml(code) {
        var safeCode = escapeHtml(code || '');
        return '<div class="code-line"><code>' + safeCode + '</code><button type="button" class="copy-btn" data-copy-text="' + safeCode + '">复制</button></div>';
    }

    function singleCodeModalHtml(detailLabel, detailValue, code, expireLabel) {
        var html = '<p class="modal-tip">提取码已生成，请立即保存并发送给接收方。</p>';
        html += codeLineHtml(code);
        html += '<p class="modal-detail"><span>' + escapeHtml(detailLabel) + '：</span>' + escapeHtml(detailValue || '-') + '</p>';
        if (expireLabel) {
            html += '<p class="modal-detail"><span>有效期：</span>' + escapeHtml(expireLabel) + '</p>';
        }
        return html;
    }

    function batchCodeModalHtml(successList, failCount) {
        var html = '<p class="modal-tip">批量上传完成：成功 ' + successList.length + '，失败 ' + failCount + '</p>';
        html += '<div class="modal-code-list">';
        for (var i = 0; i < successList.length; i++) {
            var item = successList[i];
            html += '<div class="modal-code-item">';
            html += '<strong>' + escapeHtml(item.name || ('文件' + (i + 1))) + '</strong>';
            html += codeLineHtml(item.code || '');
            if (item.expireLabel) {
                html += '<p class="modal-detail"><span>有效期：</span>' + escapeHtml(item.expireLabel) + '</p>';
            }
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    if (codeModal) {
        each(codeModal.querySelectorAll('[data-modal-close]'), function (el) {
            el.addEventListener('click', closeCodeModal);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (codeModal && !codeModal.hidden) {
                closeCodeModal();
                return;
            }

            if (window.__deleteModalState && window.__deleteModalState.isOpen && window.__deleteModalState.isOpen()) {
                window.__deleteModalState.close();
            }
        }
    });

    document.addEventListener('click', function (event) {
        if (!event.target || typeof event.target.closest !== 'function') return;

        var trigger = event.target.closest('[data-copy-text]');
        if (trigger) {
            var text = trigger.getAttribute('data-copy-text') || '';
            copyText(text).then(function () {
                var oldText = trigger.textContent;
                trigger.textContent = '已复制';
                showToast('提取码已复制到剪贴板', 'success');
                setTimeout(function () {
                    trigger.textContent = oldText || '复制';
                }, 1200);
            }).catch(function () {
                trigger.textContent = '复制失败';
                showToast('复制失败，请手动复制', 'error');
                setTimeout(function () {
                    trigger.textContent = '复制';
                }, 1200);
            });
            return;
        }

        var closeDeleteTrigger = event.target.closest('[data-close-delete-modal]');
        if (closeDeleteTrigger && window.__deleteModalState && window.__deleteModalState.close) {
            window.__deleteModalState.close();
        }
    });

    document.addEventListener('pointerdown', function (event) {
        if (!event.target || typeof event.target.closest !== 'function') return;
        var button = event.target.closest('button');
        if (!button || button.disabled || lowPower) return;

        var rect = button.getBoundingClientRect();
        var ripple = document.createElement('span');
        ripple.className = 'btn-ripple';
        ripple.style.left = (event.clientX - rect.left) + 'px';
        ripple.style.top = (event.clientY - rect.top) + 'px';
        button.appendChild(ripple);

        setTimeout(function () {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 640);
    });

    var publicTabs = document.getElementById('publicTabs');
    var publicIndicator = initTabIndicator(publicTabs, 'service-tabs-indicator');

    // 上传页第一层切换：控制“上传文件 / 上传文本”两个面板的显隐和 aria 状态。
    function updatePublicPanels(name) {
        var buttons = document.querySelectorAll('#publicTabs [data-public-tab]');
        var panels = document.querySelectorAll('[data-public-panel]');

        each(buttons, function (btn) {
            var active = btn.getAttribute('data-public-tab') === name;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        each(panels, function (panel) {
            var active = panel.getAttribute('data-public-panel') === name;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });

        requestAnimationFrame(function () {
            moveTabIndicator(publicTabs, publicIndicator, '.service-tab.is-active');
        });
    }

    if (publicTabs) {
        each(publicTabs.querySelectorAll('[data-public-tab]'), function (btn) {
            btn.addEventListener('click', function () {
                updatePublicPanels(btn.getAttribute('data-public-tab') || 'file');
            });
        });
        updatePublicPanels('file');
    }

    var modeTabs = document.getElementById('uploadModeTabs');
    var modeIndicator = initTabIndicator(modeTabs, 'mode-tabs-indicator');

    // 文件上传区第二层切换：控制拖拽、粘贴、批量三个模式面板。
    function updateModePanels(mode) {
        var buttons = document.querySelectorAll('#uploadModeTabs [data-mode]');
        var panels = document.querySelectorAll('[data-mode-panel]');

        each(buttons, function (btn) {
            var active = btn.getAttribute('data-mode') === mode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        each(panels, function (panel) {
            var active = panel.getAttribute('data-mode-panel') === mode;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });

        requestAnimationFrame(function () {
            moveTabIndicator(modeTabs, modeIndicator, '.mode-tab.is-active');
        });
    }

    if (modeTabs) {
        each(modeTabs.querySelectorAll('[data-mode]'), function (btn) {
            btn.addEventListener('click', function () {
                updateModePanels(btn.getAttribute('data-mode'));
            });
        });
    }

    updateModePanels('drag');

    window.addEventListener('resize', function () {
        moveTabIndicator(publicTabs, publicIndicator, '.service-tab.is-active');
        moveTabIndicator(modeTabs, modeIndicator, '.mode-tab.is-active');
    });

    var fileResult = document.getElementById('fileOpResult');
    var textResult = document.getElementById('textOpResult');
    var fetchResult = document.getElementById('fetchResult');

    // 后台删除记录走二次确认弹窗，避免管理端误删分享数据。
    (function initDeleteShareModal() {
        var modal = document.getElementById('deleteConfirmModal');
        var forms = document.querySelectorAll('.js-delete-form');
        var metaId = document.getElementById('deleteMetaId');
        var metaCode = document.getElementById('deleteMetaCode');
        var metaTitle = document.getElementById('deleteMetaTitle');
        var metaSize = document.getElementById('deleteMetaSize');
        var cancelBtn = document.getElementById('cancelDeleteBtn');
        var confirmBtn = document.getElementById('confirmDeleteBtn');
        var pendingForm = null;

        if (!modal || !forms.length || !cancelBtn || !confirmBtn || !metaId || !metaCode || !metaTitle || !metaSize) {
            return;
        }

        function resetModal() {
            pendingForm = null;
            metaId.textContent = '-';
            metaCode.textContent = '-';
            metaTitle.textContent = '-';
            metaSize.textContent = '-';
        }

        function openModal(form) {
            pendingForm = form;
            metaId.textContent = form.getAttribute('data-id') || '-';
            metaCode.textContent = form.getAttribute('data-code') || '-';
            metaTitle.textContent = form.getAttribute('data-title') || '-';
            metaSize.textContent = form.getAttribute('data-size') || '-';
            modal.hidden = false;
            requestAnimationFrame(function () {
                modal.classList.add('is-visible');
            });
            document.body.classList.add('modal-open');
        }

        function closeModal() {
            modal.classList.remove('is-visible');
            document.body.classList.remove('modal-open');
            setTimeout(function () {
                modal.hidden = true;
                resetModal();
            }, 150);
        }

        each(forms, function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                openModal(form);
            });
        });

        cancelBtn.addEventListener('click', closeModal);
        confirmBtn.addEventListener('click', function () {
            if (!pendingForm) {
                return;
            }
            pendingForm.submit();
        });

        window.__deleteModalState = {
            close: closeModal,
            isOpen: function () {
                return !modal.hidden;
            }
        };
    })();

    // 拖拽上传：围绕 data-dropzone 和 #dragShareForm 组织单文件上传交互。
    var dragForm = document.getElementById('dragShareForm');
    if (dragForm) {
        var dragInput = dragForm.querySelector('input[type="file"][name="file"]');
        var dropzone = dragForm.querySelector('[data-dropzone]');
        var dragFallbackFile = null;

        function syncDropzoneText() {
            var file = (dragInput && dragInput.files && dragInput.files[0]) || dragFallbackFile;
            if (file) {
                setZoneText(dropzone, '已选择：' + file.name, '大小：' + bytesToSize(file.size || 0));
                dropzone.classList.add('is-ready');
            } else {
                setZoneText(dropzone, '拖拽文件到这里', '或点击选择文件，仅支持单文件');
                dropzone.classList.remove('is-ready');
            }
        }

        if (dropzone && dragInput) {
            dropzone.addEventListener('click', function () {
                if (!dragInput.disabled) {
                    dragInput.click();
                }
            });

            dropzone.addEventListener('keydown', function (event) {
                if ((event.key === 'Enter' || event.key === ' ') && !dragInput.disabled) {
                    event.preventDefault();
                    dragInput.click();
                }
            });

            each(['dragenter', 'dragover'], function (eventName) {
                dropzone.addEventListener(eventName, function (event) {
                    if (dragInput.disabled) return;
                    event.preventDefault();
                    dropzone.classList.add('is-over');
                });
            });

            each(['dragleave', 'dragend'], function (eventName) {
                dropzone.addEventListener(eventName, function () {
                    dropzone.classList.remove('is-over');
                });
            });

            dropzone.addEventListener('drop', function (event) {
                if (dragInput.disabled) return;
                event.preventDefault();
                dropzone.classList.remove('is-over');

                var files = event.dataTransfer && event.dataTransfer.files;
                if (!files || !files.length) return;

                dragFallbackFile = files[0];
                setSingleFile(dragInput, files[0]);
                syncDropzoneText();
            });

            dragInput.addEventListener('change', function () {
                dragFallbackFile = null;
                syncDropzoneText();
            });
        }

        dragForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            var file = (dragInput && dragInput.files && dragInput.files[0]) || dragFallbackFile;
            if (!file) {
                showResult(fileResult, false, '请先选择要上传的文件。');
                showToast('请先选择文件', 'error');
                return;
            }

            showResult(fileResult, true, '上传中，请稍候...');
            try {
                var formData = new FormData(dragForm);
                formData.set('file', file, file.name || 'upload.bin');
                var response = await fetch(dragForm.action, {
                    method: dragForm.method || 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                var data = await readApiResponse(response);

                if (!data.ok) {
                    showResult(fileResult, false, escapeHtml(data.message || '上传失败'));
                    showToast(data.message || '上传失败', 'error');
                    return;
                }

                var detail = data.data || {};
                showResult(fileResult, true, '<strong>拖拽上传成功</strong><br>文件：' + escapeHtml(detail.file_name || detail.title || '-') + '<br>有效期：' + escapeHtml(detail.expire_label || '-'));
                openCodeModal('拖拽上传成功', singleCodeModalHtml('文件', detail.file_name || detail.title || '-', detail.code || '', detail.expire_label || ''));
                showToast('上传成功，提取码已生成', 'success');
                dragForm.reset();
                dragFallbackFile = null;
                syncDropzoneText();
            } catch (err) {
                showResult(fileResult, false, '请求失败，请检查网络或后端服务。');
                showToast('请求失败，请检查网络或后端服务', 'error');
            }
        });
    }

    // 粘贴上传：支持截图或剪贴板文件，和拖拽上传共用同一文件分享接口。
    var pasteForm = document.getElementById('pasteShareForm');
    if (pasteForm) {
        var pasteInput = pasteForm.querySelector('input[type="file"][name="file"]');
        var pastezone = pasteForm.querySelector('[data-pastezone]');
        var pasteHint = document.getElementById('pasteHint');
        var pastedFile = null;

        function syncPasteHint() {
            var file = (pasteInput && pasteInput.files && pasteInput.files[0]) || pastedFile;
            if (file) {
                if (pasteHint) {
                    pasteHint.textContent = '已检测到文件：' + file.name + '（' + bytesToSize(file.size || 0) + '）';
                }
                if (pastezone) {
                    pastezone.classList.add('is-ready');
                    setZoneText(pastezone, '剪贴板文件已准备好', '点击上传即可生成提取码');
                }
            } else {
                if (pasteHint) {
                    pasteHint.textContent = '当前未检测到剪贴板文件';
                }
                if (pastezone) {
                    pastezone.classList.remove('is-ready');
                    setZoneText(pastezone, '在这里按 Ctrl+V 粘贴截图/文件', '检测到文件后即可上传');
                }
            }
        }

        function readClipboardFile(event) {
            if (pasteInput && pasteInput.disabled) return;
            var clipboardData = event.clipboardData || window.clipboardData;
            if (!clipboardData) return;

            var file = null;
            if (clipboardData.items && clipboardData.items.length) {
                for (var i = 0; i < clipboardData.items.length; i++) {
                    var item = clipboardData.items[i];
                    if (item.kind === 'file') {
                        file = item.getAsFile();
                        if (file) break;
                    }
                }
            }

            if (!file && clipboardData.files && clipboardData.files.length) {
                file = clipboardData.files[0];
            }

            if (!file) return;

            pastedFile = file;
            setSingleFile(pasteInput, file);
            syncPasteHint();
            event.preventDefault();
        }

        if (pastezone) {
            pastezone.addEventListener('click', function () {
                pastezone.focus();
            });

            pastezone.addEventListener('paste', readClipboardFile);

            each(['dragenter', 'dragover'], function (eventName) {
                pastezone.addEventListener(eventName, function (event) {
                    if (pasteInput.disabled) return;
                    event.preventDefault();
                    pastezone.classList.add('is-over');
                });
            });

            each(['dragleave', 'dragend'], function (eventName) {
                pastezone.addEventListener(eventName, function () {
                    pastezone.classList.remove('is-over');
                });
            });

            pastezone.addEventListener('drop', function (event) {
                if (pasteInput.disabled) return;
                event.preventDefault();
                pastezone.classList.remove('is-over');

                var files = event.dataTransfer && event.dataTransfer.files;
                if (!files || !files.length) return;

                pastedFile = files[0];
                setSingleFile(pasteInput, files[0]);
                syncPasteHint();
            });
        }

        if (pasteInput) {
            pasteInput.addEventListener('change', function () {
                pastedFile = null;
                syncPasteHint();
            });
        }

        pasteForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            var file = (pasteInput && pasteInput.files && pasteInput.files[0]) || pastedFile;
            if (!file) {
                showResult(fileResult, false, '请先粘贴一个文件或截图。');
                showToast('请先粘贴文件或截图', 'error');
                return;
            }

            showResult(fileResult, true, '上传中，请稍候...');
            try {
                var formData = new FormData(pasteForm);
                formData.set('file', file, file.name || 'clipboard-file');

                var response = await fetch(pasteForm.action, {
                    method: pasteForm.method || 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                var data = await readApiResponse(response);

                if (!data.ok) {
                    showResult(fileResult, false, escapeHtml(data.message || '上传失败'));
                    showToast(data.message || '上传失败', 'error');
                    return;
                }

                var detail = data.data || {};
                showResult(fileResult, true, '<strong>粘贴上传成功</strong><br>文件：' + escapeHtml(detail.file_name || detail.title || '-') + '<br>有效期：' + escapeHtml(detail.expire_label || '-'));
                openCodeModal('粘贴上传成功', singleCodeModalHtml('文件', detail.file_name || detail.title || '-', detail.code || '', detail.expire_label || ''));
                showToast('上传成功，提取码已生成', 'success');
                pasteForm.reset();
                pastedFile = null;
                syncPasteHint();
            } catch (err) {
                showResult(fileResult, false, '请求失败，请检查网络或后端服务。');
                showToast('请求失败，请检查网络或后端服务', 'error');
            }
        });
    }

    // 批量上传：前端逐个串行提交文件，便于展示每个文件独立的提取码和失败状态。
    var batchForm = document.getElementById('batchShareForm');
    if (batchForm) {
        var batchInput = batchForm.querySelector('input[type="file"][name="file"]');
        var batchQueue = document.getElementById('batchQueue');
        var batchProgress = document.getElementById('batchProgress');
        var batchProgressMeta = batchProgress ? batchProgress.querySelector('.batch-progress-meta') : null;
        var batchProgressFill = batchProgress ? batchProgress.querySelector('.batch-progress-fill') : null;

        function setBatchProgress(text, isEmpty, ratio) {
            if (!batchProgress) return;
            if (batchProgressMeta) {
                batchProgressMeta.textContent = text;
            } else {
                batchProgress.textContent = text;
            }
            batchProgress.className = 'batch-progress' + (isEmpty ? ' empty' : '');

            if (batchProgressFill) {
                var percent = Math.max(0, Math.min(100, Math.round((ratio || 0) * 100)));
                batchProgressFill.style.width = percent + '%';
            }
        }

        function getBatchFiles() {
            if (!batchInput || !batchInput.files) return [];
            return Array.prototype.slice.call(batchInput.files);
        }

        function renderBatchQueue(files) {
            if (!batchQueue) return;
            if (!files.length) {
                batchQueue.className = 'batch-queue empty';
                batchQueue.textContent = '未选择文件';
                return;
            }

            var html = [];
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                html.push(
                    '<div class="batch-item" id="batchItem-' + i + '">' +
                    '<div class="batch-item-head"><strong>' + escapeHtml(file.name) + '</strong><span>' + escapeHtml(bytesToSize(file.size || 0)) + '</span></div>' +
                    '<div class="batch-item-track"><span class="batch-item-fill" style="width:0%"></span></div>' +
                    '<p class="batch-status">等待上传</p>' +
                    '</div>'
                );
            }

            batchQueue.className = 'batch-queue';
            batchQueue.innerHTML = html.join('');
        }

        function updateBatchItem(index, status, message, ratio) {
            var item = document.getElementById('batchItem-' + index);
            if (!item) return;
            item.className = 'batch-item' + (status ? ' ' + status : '');
            var text = item.querySelector('.batch-status');
            var fill = item.querySelector('.batch-item-fill');
            if (text) {
                text.textContent = message;
            }
            if (fill) {
                var percent = Math.max(0, Math.min(100, Math.round((ratio || 0) * 100)));
                fill.style.width = percent + '%';
            }
        }

        if (batchInput) {
            batchInput.addEventListener('change', function () {
                var files = getBatchFiles();
                renderBatchQueue(files);
                setBatchProgress('已选择 ' + files.length + ' 个文件，等待开始。', false, 0);
            });
        }

        batchForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            var files = getBatchFiles();
            if (!files.length) {
                setBatchProgress('请先选择至少一个文件。', false, 0);
                showResult(fileResult, false, '请先选择至少一个文件。');
                showToast('请先选择至少一个文件', 'error');
                return;
            }

            var prefixInput = batchForm.querySelector('input[name="title_prefix"]');
            var titlePrefix = prefixInput ? String(prefixInput.value || '').trim() : '';

            var okCount = 0;
            var failCount = 0;
            var successList = [];

            showResult(fileResult, true, '批量上传进行中，请稍候...');
            setBatchProgress('批量上传启动中...', false, 0);

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                updateBatchItem(i, '', '上传中...', 0.35);
                setBatchProgress('正在上传 ' + (i + 1) + '/' + files.length + ' ...', false, i / files.length);

                try {
                    var formData = new FormData(batchForm);
                    formData.delete('title_prefix');
                    formData.set('file', file, file.name || ('file-' + (i + 1)));
                    formData.set('title', titlePrefix ? (titlePrefix + '-' + file.name) : file.name);

                    var response = await fetch(batchForm.action, {
                        method: batchForm.method || 'POST',
                        body: formData,
                        headers: { 'Accept': 'application/json' }
                    });
                    var data = await readApiResponse(response);

                    if (data.ok) {
                        okCount += 1;
                        var code = data.data && data.data.code ? data.data.code : '';
                        var expireLabel = data.data && data.data.expire_label ? data.data.expire_label : '';
                        updateBatchItem(i, 'ok', '上传成功，提取码：' + (code || '-') + (expireLabel ? ' / ' + expireLabel : ''), 1);
                        successList.push({ name: file.name, code: code, expireLabel: expireLabel });
                    } else {
                        failCount += 1;
                        updateBatchItem(i, 'err', data.message || '上传失败', 1);
                    }
                } catch (err) {
                    failCount += 1;
                    updateBatchItem(i, 'err', '请求失败，请稍后重试', 1);
                }

                setBatchProgress('正在上传 ' + (i + 1) + '/' + files.length + ' ...', false, (i + 1) / files.length);
            }

            setBatchProgress('批量上传完成：成功 ' + okCount + '，失败 ' + failCount, false, 1);
            if (failCount > 0) {
                showResult(fileResult, false, '<strong>批量上传完成</strong><br>成功：' + okCount + '，失败：' + failCount);
                showToast('批量上传完成，存在失败项', 'error');
            } else {
                showResult(fileResult, true, '<strong>批量上传完成</strong><br>成功：' + okCount + '，失败：0');
                showToast('批量上传完成', 'success');
            }

            if (successList.length > 0) {
                openCodeModal('批量上传提取码', batchCodeModalHtml(successList, failCount));
            }
        });
    }

    // 文本分享：和文件上传分离展示，但仍复用统一的接口解析和结果弹窗逻辑。
    var textForm = document.getElementById('textShareForm');
    if (textForm) {
        textForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            showResult(textResult, true, '提交中，请稍候...');
            try {
                var data = await submitForm(textForm);
                if (!data.ok) {
                    showResult(textResult, false, escapeHtml(data.message || '提交失败'));
                    showToast(data.message || '提交失败', 'error');
                    return;
                }

                var detail = data.data || {};
                showResult(
                    textResult,
                    true,
                    '<strong>文本分享成功</strong><br>标题：' + escapeHtml(detail.title || '-') + '<br>有效期：' + escapeHtml(detail.expire_label || '-')
                );
                openCodeModal('文本分享成功', singleCodeModalHtml('标题', detail.title || '-', detail.code || '', detail.expire_label || ''));
                showToast('文本分享成功，提取码已生成', 'success');
                textForm.reset();
            } catch (err) {
                showResult(textResult, false, '请求失败，请检查网络或后端服务。');
                showToast('请求失败，请检查网络或后端服务', 'error');
            }
        });
    }

    // 首页取件入口：根据 share_type 区分渲染文本正文或文件下载链接。
    var fetchForm = document.getElementById('fetchForm');
    if (fetchForm) {
        fetchForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            showResult(fetchResult, true, '查询中，请稍候...');
            try {
                var data = await submitForm(fetchForm);
                if (!data.ok) {
                    showResult(fetchResult, false, escapeHtml(data.message || '提取失败'));
                    showToast(data.message || '提取失败', 'error');
                    return;
                }

                var detail = data.data || {};
                if (detail.share_type === 'text') {
                    var text = '<strong>文本内容</strong><br><pre>' + escapeHtml(detail.text_content || '') + '</pre>';
                    text += '<br><small>标题：' + escapeHtml(detail.title || '-') + ' / 创建时间：' + escapeHtml(detail.created_at || '-') + ' / 有效期：' + escapeHtml(detail.expire_label || '-') + '</small>';
                    showResult(fetchResult, true, text);
                    showToast('文本取件成功', 'success');
                } else {
                    var html = '<strong>文件已找到</strong><br>';
                    html += '文件名：' + escapeHtml(detail.file_name || detail.title || '-') + '<br>';
                    html += '大小：' + escapeHtml(bytesToSize(detail.file_size || 0)) + '<br>';
                    html += '有效期：' + escapeHtml(detail.expire_label || '-') + '<br>';
                    html += '<a href="' + escapeHtml(detail.download_url || '#') + '">点击下载文件</a>';
                    showResult(fetchResult, true, html);
                    showToast('文件已找到，可点击下载', 'success');
                }
            } catch (err) {
                showResult(fetchResult, false, '请求失败，请检查网络或后端服务。');
                showToast('请求失败，请检查网络或后端服务', 'error');
            }
        });
    }
})();
