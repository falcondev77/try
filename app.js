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

                var debugLogHtml = '';
                if (data.price_debug_log && data.price_debug_log.length > 0) {
                    var logLines = data.price_debug_log.map(function (line) {
                        var cssClass = 'debug-line';
                        if (line.indexOf('** SELEZIONATO') !== -1 || line.indexOf('PREZZO FINALE') !== -1) {
                            cssClass += ' debug-success';
                        } else if (line.indexOf('ERRORE') !== -1 || line.indexOf('NESSUN PREZZO') !== -1) {
                            cssClass += ' debug-error';
                        } else if (line.indexOf('non trovato') !== -1 || line.indexOf('BARRATO') !== -1) {
                            cssClass += ' debug-warn';
                        } else if (line.indexOf('===') !== -1) {
                            cssClass += ' debug-header';
                        }
                        return '<div class="' + cssClass + '">' + escapeHtml(line) + '</div>';
                    }).join('');

                    debugLogHtml =
                        '<div class="debug-log-section">' +
                            '<div class="debug-log-toggle" onclick="this.parentElement.classList.toggle(\'open\')">' +
                                '<span class="debug-log-icon">&#9654;</span> Debug estrazione prezzo (' + data.price_debug_log.length + ' righe)' +
                            '</div>' +
                            '<div class="debug-log-content">' + logLines + '</div>' +
                        '</div>';
                }

                resultBox.innerHTML =
                    '<div class="result-product-title">' + escapeHtml(data.title || 'Prodotto Amazon') + '</div>' +
                    '<div class="result-meta">' + metaHtml + '</div>' +
                    '<div class="result-actions">' +
                        '<a class="btn-result-primary" href="' + escapeHtml(data.go_url) + '">Vai su Amazon &rarr;</a>' +
                        '<a class="btn-result-secondary" href="' + escapeHtml(data.affiliate_url) + '" target="_blank" rel="noopener">Apri link diretto</a>' +
                    '</div>' +
                    debugLogHtml;
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
