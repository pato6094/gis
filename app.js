document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('analyzeBtn');
    const input = document.getElementById('amazonUrl');
    const category = document.getElementById('categorySlug');
    const resultBox = document.getElementById('resultBox');

    if (!btn || !input || !resultBox) {
        return;
    }

    btn.addEventListener('click', function () {
        const url = input.value.trim();
        if (!url) {
            renderError('Inserisci un link Amazon valido.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Calcolo in corso...';
        resultBox.classList.remove('hidden');
        resultBox.innerHTML = '<div class="small">Sto analizzando il link...</div>';

        const formData = new FormData();
        formData.append('url', url);
        if (category) {
            formData.append('category_slug', category.value);
        }

        fetch('api_convert.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(async function (response) {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Errore durante l\'analisi del link.');
                }
                return data;
            })
            .then(function (data) {
                const price = data.price_label || 'Prezzo non disponibile';
                const calc = data.calculation || null;
                const categoryRule = data.category_rule || null;
                const details = calc ? `
                    <p><strong>Categoria:</strong> ${escapeHtml(categoryRule.category_name || '-')}</p>
                    <p><strong>Commissione Amazon:</strong> ${Number(calc.amazon_rate).toLocaleString('it-IT')}%</p>
                    <p><strong>Quota utente:</strong> ${Number(calc.share_percent).toLocaleString('it-IT')}%</p>
                    <p><strong>Tua commissione stimata:</strong> ${formatEuro(calc.amazon_commission)}</p>
                    <p><strong>Valore utente:</strong> ${formatEuro(calc.user_value)}</p>
                ` : '';

                resultBox.innerHTML = `
                    <h3>${escapeHtml(data.title || 'Prodotto Amazon')}</h3>
                    <p><strong>ASIN:</strong> ${escapeHtml(data.asin)}</p>
                    <p><strong>Prezzo rilevato:</strong> ${escapeHtml(price)}</p>
                    <p><strong>Punti previsti:</strong> <span class="points">${Number(data.points).toLocaleString('it-IT')}</span></p>
                    ${details}
                    <p><strong>Tag affiliato:</strong> ${escapeHtml(data.tag)}</p>
                    <div class="actions">
                        <a class="btn" href="${escapeHtml(data.go_url)}">Vai su Amazon con link affiliato</a>
                        <a class="btn btn-light" href="${escapeHtml(data.affiliate_url)}" target="_blank" rel="noopener">Apri link diretto</a>
                    </div>
                    <p class="small">Calcolo: prezzo × commissione Amazon × quota utente × 100.</p>
                `;
            })
            .catch(function (error) {
                renderError(error.message || 'Errore imprevisto.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = 'Calcola punti';
            });
    });

    function renderError(message) {
        resultBox.classList.remove('hidden');
        resultBox.innerHTML = `<div class="alert error">${escapeHtml(message)}</div>`;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatEuro(value) {
        const number = Number(value || 0);
        return '€ ' + number.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
});
