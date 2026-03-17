document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('analyzeBtn');
    const input = document.getElementById('amazonUrl');
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
        btn.textContent = 'Analisi in corso...';
        resultBox.classList.remove('hidden');
        resultBox.innerHTML = '<div style="color:#525252;font-size:14px;">Sto analizzando il link...</div>';

        const formData = new FormData();
        formData.append('url', url);

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
                const calc = data.calculation || null;
                const categoryRule = data.category_rule || null;

                const metaItems = [
                    { label: 'Prezzo', value: data.price_label || 'N/D', accent: false },
                    { label: 'Categoria', value: categoryRule ? categoryRule.category_name : '-', accent: false },
                    { label: 'Commissione', value: categoryRule ? Number(categoryRule.amazon_rate).toLocaleString('it-IT') + '%' : '-', accent: false },
                    { label: 'Punti previsti', value: Number(data.points).toLocaleString('it-IT'), accent: true },
                ];

                const metaHtml = metaItems.map(function (item) {
                    return '<div class="result-meta-item">' +
                        '<div class="result-meta-label">' + escapeHtml(item.label) + '</div>' +
                        '<div class="result-meta-value' + (item.accent ? ' accent' : '') + '">' + escapeHtml(String(item.value)) + '</div>' +
                        '</div>';
                }).join('');

                resultBox.innerHTML =
                    '<div class="result-product-title">' + escapeHtml(data.title || 'Prodotto Amazon') + '</div>' +
                    '<div class="result-meta">' + metaHtml + '</div>' +
                    '<div class="result-actions">' +
                        '<a class="btn-result-primary" href="' + escapeHtml(data.go_url) + '">Vai su Amazon &rarr;</a>' +
                        '<a class="btn-result-secondary" href="' + escapeHtml(data.affiliate_url) + '" target="_blank" rel="noopener">Apri link diretto</a>' +
                    '</div>';
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
        resultBox.innerHTML = '<div class="result-alert-error">' + escapeHtml(message) + '</div>';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
