document.querySelectorAll('[data-password-toggle]').forEach(button => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    const fieldName = input.name === 'password_confirmation' ? 'password confirmation' : 'password';

    button.hidden = false;
    button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide' : 'Show';
        button.setAttribute('aria-label', `${button.textContent} ${fieldName}`);
    });
});

const authForm = document.querySelector('[data-auth-form]');
const authLoading = document.querySelector('[data-auth-loading]');
if (authForm && authLoading) {
    const submitButton = authForm.querySelector('button[type="submit"], button:not([type])');
    authForm.addEventListener('submit', event => {
        if (!authLoading.hidden) {
            event.preventDefault();
            return;
        }
        authLoading.hidden = false;
        submitButton.disabled = true;
    });
    window.addEventListener('pageshow', () => {
        authLoading.hidden = true;
        submitButton.disabled = false;
    });
}
