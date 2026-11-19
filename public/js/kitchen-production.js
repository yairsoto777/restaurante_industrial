
(() => {
    'use strict';

    // Mantener el comportamiento actual del detalle de comanda.
    const detail = document.querySelector('[data-kp-detail]');

    if (detail && typeof detail.showModal === 'function') {
        detail.removeAttribute('open');
        detail.showModal();

        const close = detail.querySelector('[data-kp-close]');

        detail.addEventListener('cancel', event => {
            event.preventDefault();

            if (close?.href) {
                location.assign(close.href);
            }
        });
    }

    const workspace = document.querySelector('.kp-workspace');
    if (!workspace) return;

    // Estilos exclusivos de la confirmación de entrega.
    const styles = document.createElement('style');
    styles.textContent = `
        .kp-delivery-dialog {
            width: min(460px, calc(100% - 32px));
            max-width: 460px;
            max-height: 90vh;
            padding: 0;
            border: none;
            border-radius: 20px;
            background: #ffffff;
            color: #233348;
            box-shadow: 0 24px 80px rgba(7, 22, 43, .32);
            overflow: auto;
            font-family: inherit;
        }

        .kp-delivery-dialog::backdrop {
            background: rgba(9, 24, 43, .64);
            backdrop-filter: blur(3px);
        }

        .kp-delivery-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 23px 25px;
            background: #17375e;
            color: #ffffff;
        }

        .kp-delivery-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: rgba(255,255,255,.15);
            font-size: 25px;
        }

        .kp-delivery-title {
            margin: 0;
            color: #ffffff;
            font-size: 19px;
            line-height: 1.3;
            font-weight: 750;
        }

        .kp-delivery-subtitle {
            margin: 5px 0 0;
            color: #d6e2f0;
            font-size: 12px;
        }

        .kp-delivery-body {
            padding: 25px;
        }

        .kp-delivery-question {
            margin: 0 0 19px;
            font-size: 15px;
            line-height: 1.65;
            color: #334155;
        }

        .kp-delivery-notice {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 14px;
            border: 1px solid #f2dfb4;
            border-radius: 12px;
            background: #fffaf0;
            color: #815a20;
            font-size: 13px;
            line-height: 1.6;
        }

        .kp-delivery-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        .kp-delivery-btn {
            min-height: 43px;
            padding: 10px 17px;
            border-radius: 10px;
            border: 1px solid transparent;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s, box-shadow .15s;
        }

        .kp-delivery-btn:focus-visible {
            outline: 3px solid #8bb6e9;
            outline-offset: 3px;
        }

        .kp-delivery-cancel {
            background: #ffffff;
            border-color: #d5dfeb;
            color: #42536a;
        }

        .kp-delivery-cancel:hover {
            background: #f1f5f9;
        }

        .kp-delivery-accept {
            background: #17375e;
            color: #ffffff;
        }

        .kp-delivery-accept:hover {
            background: #244e7d;
        }

        @media (max-width: 480px) {
            .kp-delivery-header,
            .kp-delivery-body {
                padding: 19px;
            }

            .kp-delivery-actions {
                flex-direction: column-reverse;
            }

            .kp-delivery-btn {
                width: 100%;
            }
        }
    `;

    document.head.appendChild(styles);

    // Crear el modal fuera del formulario original.
    const confirmation = document.createElement('dialog');

    confirmation.className = 'kp-delivery-dialog';
    confirmation.setAttribute(
        'aria-labelledby',
        'kp-delivery-heading'
    );

    confirmation.innerHTML = `
        <div class="kp-delivery-header">
            <span class="kp-delivery-icon" aria-hidden="true">✓</span>

            <div>
                <h2 id="kp-delivery-heading"
                    class="kp-delivery-title">
                    Confirmar entrega
                </h2>

                <p class="kp-delivery-subtitle">
                    NUTRILIADO · Personal de cocina
                </p>
            </div>
        </div>

        <div class="kp-delivery-body">
            <p class="kp-delivery-question">
                ¿Deseas registrar la entrega de esta partida?
            </p>

            <div class="kp-delivery-notice">
                <span aria-hidden="true">⚠</span>

                <span>
                    Antes de confirmar, verifica que los
                    platillos estén completos y que se
                    hayan respetado las observaciones
                    y restricciones de alérgenos.
                </span>
            </div>

            <div class="kp-delivery-actions">
                <button type="button"
                        class="kp-delivery-btn kp-delivery-cancel"
                        data-delivery-cancel>
                    Cancelar
                </button>

                <button type="button"
                        class="kp-delivery-btn kp-delivery-accept"
                        data-delivery-accept>
                    ✓ Confirmar entrega
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(confirmation);

    const cancelButton = confirmation.querySelector(
        '[data-delivery-cancel]'
    );

    const acceptButton = confirmation.querySelector(
        '[data-delivery-accept]'
    );

    let pendingSubmission = null;

    // Cancelar nunca envía el formulario.
    cancelButton.addEventListener('click', () => {
        confirmation.close();
    });

    confirmation.addEventListener('close', () => {
        pendingSubmission = null;
    });

    // Confirmar retoma el envío normal de Laravel.
    acceptButton.addEventListener('click', () => {
        const submission = pendingSubmission;
        if (!submission) return;

        const { form, button } = submission;

        confirmation.close();

        if (form.dataset.busy === 'true') return;

        // Autorizar únicamente el siguiente envío.
        form.dataset.kpApproved = 'true';

        try {
            if (button) {
                form.requestSubmit(button);
            } else {
                form.requestSubmit();
            }
        } catch (error) {
            delete form.dataset.kpApproved;
            throw error;
        }
    });

    workspace.addEventListener('submit', event => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) return;
        if (!form.matches('[data-kp-action]')) return;

        // Evitar envíos duplicados.
        if (form.dataset.busy === 'true') {
            event.preventDefault();
            return;
        }

        // Mostrar confirmación únicamente si está solicitada.
        if (
            form.hasAttribute('data-kp-confirm') &&
            form.dataset.kpApproved !== 'true'
        ) {
            event.preventDefault();

            const button =
                event.submitter ||
                form.querySelector(
                    'button[type="submit"], button:not([type])'
                );

            pendingSubmission = { form, button };

            if (!confirmation.open) {
                confirmation.showModal();
            }

            return;
        }

        // Continuar con el envío original.
        delete form.dataset.kpApproved;

        form.dataset.busy = 'true';

        const button =
            event.submitter ||
            form.querySelector(
                'button[type="submit"], button:not([type])'
            );

        if (button) {
            button.disabled = true;
        }
    });
})();
