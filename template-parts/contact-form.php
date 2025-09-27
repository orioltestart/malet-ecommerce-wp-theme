<?php
/**
 * Contact Form Template Part
 * Formulari de contacte personalitzat seguint l'estil del frontend
 *
 * @package Malet Torrent
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="malet-contact-form-wrapper">
    <div class="contact-form-container">
        <div class="form-header">
            <h2>Envia'ns un missatge</h2>
            <p>Estem aquí per ajudar-te amb qualsevol consulta sobre els nostres melindros artesans</p>
        </div>

        <div class="form-content">
            <?php echo do_shortcode('[contact-form-7 id="1" title="Formulari de contacte Malet Torrent"]'); ?>
        </div>
    </div>
</div>

<style>
/* Estils del formulari de contacte inspirats en el frontend */
.malet-contact-form-wrapper {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.contact-form-container {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    border: 1px solid #e9ecef;
}

.form-header {
    background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
    color: white;
    padding: 40px 30px;
    text-align: center;
}

.form-header h2 {
    font-size: 28px;
    font-weight: 600;
    margin: 0 0 10px 0;
}

.form-header p {
    font-size: 16px;
    opacity: 0.9;
    margin: 0;
    font-weight: 300;
}

.form-content {
    padding: 40px 30px;
}

/* Estils per Contact Form 7 */
.wpcf7 {
    margin: 0;
}

.wpcf7-form {
    display: grid;
    gap: 25px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 500;
    color: #333333;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group label::before {
    content: '';
    width: 20px;
    height: 20px;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    opacity: 0.7;
}

.form-group.name label::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23666' viewBox='0 0 24 24'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3E");
}

.form-group.email label::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23666' viewBox='0 0 24 24'%3E%3Cpath d='M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.89 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z'/%3E%3C/svg%3E");
}

.form-group.phone label::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23666' viewBox='0 0 24 24'%3E%3Cpath d='M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z'/%3E%3C/svg%3E");
}

.form-group.subject label::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23666' viewBox='0 0 24 24'%3E%3Cpath d='M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 2 2h16c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z'/%3E%3C/svg%3E");
}

.form-group.message label::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23666' viewBox='0 0 24 24'%3E%3Cpath d='M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z'/%3E%3C/svg%3E");
}

.wpcf7-form-control-wrap {
    display: block;
    position: relative;
    width: 100%;
}

.wpcf7-form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 16px;
    font-family: inherit;
    transition: all 0.3s ease;
    background-color: #ffffff;
}

.wpcf7-form-control:focus {
    outline: none;
    border-color: #8B4513;
    box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
}

.wpcf7-form-control::placeholder {
    color: #6c757d;
    font-size: 14px;
}

.wpcf7-textarea {
    min-height: 120px;
    resize: vertical;
    font-family: inherit;
    line-height: 1.5;
}

.wpcf7-submit {
    background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 20px auto 0;
    min-width: 200px;
}

.wpcf7-submit:hover {
    background: linear-gradient(135deg, #A0522D 0%, #8B4513 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
}

.wpcf7-submit:active {
    transform: translateY(0);
}

.wpcf7-submit::before {
    content: '';
    width: 20px;
    height: 20px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='white' viewBox='0 0 24 24'%3E%3Cpath d='M2.01 21L23 12 2.01 3 2 10l15 2-15 2z'/%3E%3C/svg%3E");
    background-size: contain;
    background-repeat: no-repeat;
}

/* Missatges de resposta */
.wpcf7-response-output {
    margin: 20px 0 0 0;
    padding: 15px;
    border-radius: 8px;
    font-weight: 500;
    text-align: center;
}

.wpcf7-mail-sent-ok {
    background-color: #d4edda;
    border: 2px solid #28a745;
    color: #155724;
}

.wpcf7-mail-sent-ng,
.wpcf7-validation-errors {
    background-color: #f8d7da;
    border: 2px solid #dc3545;
    color: #721c24;
}

.wpcf7-spam-blocked {
    background-color: #fff3cd;
    border: 2px solid #ffc107;
    color: #856404;
}

/* Missatges de validació */
.wpcf7-not-valid-tip {
    color: #dc3545;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.wpcf7-not-valid {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
}

/* Spinner de càrrega */
.wpcf7-spinner {
    margin-left: 10px;
    opacity: 0.75;
}

/* Nota de privacitat */
.privacy-note {
    background-color: #f8f9fa;
    border-left: 4px solid #8B4513;
    padding: 15px;
    margin-top: 20px;
    border-radius: 0 8px 8px 0;
    font-size: 14px;
    color: #6c757d;
    text-align: center;
}

.privacy-note a {
    color: #8B4513;
    text-decoration: none;
    font-weight: 500;
}

.privacy-note a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 768px) {
    .malet-contact-form-wrapper {
        margin: 20px auto;
        padding: 0 15px;
    }

    .form-header {
        padding: 30px 20px;
    }

    .form-header h2 {
        font-size: 24px;
    }

    .form-content {
        padding: 30px 20px;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .wpcf7-form {
        gap: 20px;
    }

    .wpcf7-submit {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .form-header h2 {
        font-size: 22px;
    }

    .form-header p {
        font-size: 14px;
    }

    .wpcf7-form-control {
        padding: 10px 14px;
        font-size: 14px;
    }

    .wpcf7-submit {
        padding: 12px 24px;
        font-size: 14px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Millorar l'experiència del formulari
    const form = document.querySelector('.wpcf7-form');
    if (form) {
        // Afegir labels visuals als camps
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            const wrapper = input.closest('.wpcf7-form-control-wrap');
            if (wrapper) {
                const parent = wrapper.parentElement;

                // Afegir classe segons el tipus de camp
                if (input.name.includes('your-name')) {
                    parent.classList.add('form-group', 'name');
                    if (!parent.querySelector('label')) {
                        const label = document.createElement('label');
                        label.textContent = 'Nom complet *';
                        label.setAttribute('for', input.id);
                        parent.insertBefore(label, wrapper);
                    }
                } else if (input.name.includes('your-email')) {
                    parent.classList.add('form-group', 'email');
                    if (!parent.querySelector('label')) {
                        const label = document.createElement('label');
                        label.textContent = 'Correu electrònic *';
                        label.setAttribute('for', input.id);
                        parent.insertBefore(label, wrapper);
                    }
                } else if (input.name.includes('your-phone')) {
                    parent.classList.add('form-group', 'phone');
                    if (!parent.querySelector('label')) {
                        const label = document.createElement('label');
                        label.textContent = 'Telèfon';
                        label.setAttribute('for', input.id);
                        parent.insertBefore(label, wrapper);
                    }
                } else if (input.name.includes('your-subject')) {
                    parent.classList.add('form-group', 'subject');
                    if (!parent.querySelector('label')) {
                        const label = document.createElement('label');
                        label.textContent = 'Assumpte *';
                        label.setAttribute('for', input.id);
                        parent.insertBefore(label, wrapper);
                    }
                } else if (input.name.includes('your-message')) {
                    parent.classList.add('form-group', 'message');
                    if (!parent.querySelector('label')) {
                        const label = document.createElement('label');
                        label.textContent = 'Missatge *';
                        label.setAttribute('for', input.id);
                        parent.insertBefore(label, wrapper);
                    }
                }
            }
        });

        // Agrupar camps en files
        const nameField = form.querySelector('[name*="your-name"]')?.closest('.form-group');
        const emailField = form.querySelector('[name*="your-email"]')?.closest('.form-group');
        const phoneField = form.querySelector('[name*="your-phone"]')?.closest('.form-group');
        const subjectField = form.querySelector('[name*="your-subject"]')?.closest('.form-group');

        if (nameField && emailField) {
            const row1 = document.createElement('div');
            row1.className = 'form-row';
            nameField.parentNode.insertBefore(row1, nameField);
            row1.appendChild(nameField);
            row1.appendChild(emailField);
        }

        if (phoneField && subjectField) {
            const row2 = document.createElement('div');
            row2.className = 'form-row';
            phoneField.parentNode.insertBefore(row2, phoneField);
            row2.appendChild(phoneField);
            row2.appendChild(subjectField);
        }

        // Afegir nota de privacitat
        const submitButton = form.querySelector('.wpcf7-submit');
        if (submitButton && !form.querySelector('.privacy-note')) {
            const privacyNote = document.createElement('div');
            privacyNote.className = 'privacy-note';
            privacyNote.innerHTML = '* Camps obligatoris. Les vostres dades seran tractades segons la nostra <a href="/politica-privacitat" target="_blank">política de privacitat</a>.';
            submitButton.parentNode.insertBefore(privacyNote, submitButton.nextSibling);
        }

        // Efectes visuals
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
                this.parentElement.style.transition = 'transform 0.2s ease';
            });

            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    }
});
</script>