/**
 * Asistente de Búsqueda por Voz - Carnicerías "El Cinco" (Carnicería Cano)
 * Desarrollado con Web Speech API (SpeechRecognition / webkitSpeechRecognition)
 * y respuesta de voz opcional (SpeechSynthesis).
 */
(function () {
    'use strict';

    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
    var recognition = null;
    var isListening = false;
    var currentRawTranscript = '';
    var currentCleanedQuery = '';
    var currentDetectedQty = null;
    var searchTriggered = false;
    var voiceModal = null;
    var silenceTimer = null;

    document.addEventListener('DOMContentLoaded', function () {
        initVoiceAssistant();
    });

    function initVoiceAssistant() {
        var modalEl = document.getElementById('voiceSearchModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            voiceModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true });

            modalEl.addEventListener('hidden.bs.modal', function () {
                stopListening(false);
            });
        }

        // Delegación de clic para cualquier botón con clase .btn-voice-search
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-voice-search');
            if (!btn) return;
            e.preventDefault();
            openVoiceModal();
        });

        // Botón central de micrófono dentro del modal
        var micModalBtn = document.getElementById('voiceModalMicBtn');
        if (micModalBtn) {
            micModalBtn.addEventListener('click', function () {
                if (isListening) {
                    stopListening(true);
                } else {
                    startListening();
                }
            });
        }

        // Botón "Buscar" manual en el modal
        var submitBtn = document.getElementById('voiceModalSubmitBtn');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                triggerAutoSearch();
            });
        }

        // Chips de sugerencias populares dentro del modal
        document.addEventListener('click', function (e) {
            var chip = e.target.closest('.voice-suggestion-chip');
            if (!chip) return;
            var term = chip.getAttribute('data-search') || chip.textContent.trim();
            if (term) {
                searchTriggered = true;
                stopListening(false);
                executeSearch(term);
            }
        });
    }

    /**
     * Extrae cantidades naturales habladas (ej: "medio de", "1/2", "kilo y medio", "un cuarto")
     */
    function extractVoiceQuantity(text) {
        if (!text) return { qty: null, term: text };
        var cleaned = text.trim();
        var qty = null;

        var patterns = [
            // Fracciones compuestas (kilo y medio, kilogramo y medio)
            { rgx: /^(?:(?:un|1)\s+)?(?:kilos?|kilogramos?|kg)\s+y\s+medio(?:\s+de)?\s+/i, val: 1.5 },
            { rgx: /^(?:(?:dos|2)\s+)?(?:kilos|kilogramos|kg)\s+y\s+medio(?:\s+de)?\s+/i, val: 2.5 },
            { rgx: /^(?:(?:tres|3)\s+)?(?:kilos|kilogramos|kg)\s+y\s+medio(?:\s+de)?\s+/i, val: 3.5 },

            // Tres cuartos
            { rgx: /^(?:tres\s+cuartos|3\/4)(?:\s+de)?(?:\s+(?:kilos?|kilogramos?|kgs?|k))?(?:\s+de)?\s+/i, val: 0.75 },

            // Medio kilo / medio kilogramo / medio de / 1/2
            { rgx: /^(?:medio|1\/2)(?:\s+de)?(?:\s+(?:kilos?|kilogramos?|kgs?|k))?(?:\s+de)?\s+/i, val: 0.5 },

            // Un cuarto / 1/4
            { rgx: /^(?:(?:un\s+)?cuarto|1\/4)(?:\s+de)?(?:\s+(?:kilos?|kilogramos?|kgs?|k))?(?:\s+de)?\s+/i, val: 0.25 },

            // Kilos enteros
            { rgx: /^(?:un|uno|1)\s+(?:kilos?|kilogramos?|kg)(?:\s+de)?\s+/i, val: 1.0 },
            { rgx: /^(?:kilos?|kilogramos?)\s+(?:de\s+)?/i, val: 1.0 },
            { rgx: /^(?:dos|2)\s+(?:kilos|kilogramos|kg|k)(?:\s+de)?\s+/i, val: 2.0 },
            { rgx: /^(?:tres|3)\s+(?:kilos|kilogramos|kg|k)(?:\s+de)?\s+/i, val: 3.0 },
            { rgx: /^(?:cuatro|4)\s+(?:kilos|kilogramos|kg|k)(?:\s+de)?\s+/i, val: 4.0 },
            { rgx: /^(?:cinco|5)\s+(?:kilos|kilogramos|kg|k)(?:\s+de)?\s+/i, val: 5.0 },
            { rgx: /^(\d+(?:\.\d+)?)\s*(?:kilos?|kilogramos?|kgs?|k)(?:\s+de)?\s+/i, dynamic: 'kg' },

            // Gramos
            { rgx: /^(\d+)\s*(?:gramos?|grs?|gr|g)(?:\s+de)?\s+/i, dynamic: 'gr' }
        ];

        for (var i = 0; i < patterns.length; i++) {
            var item = patterns[i];
            var m = cleaned.match(item.rgx);
            if (m) {
                if (item.dynamic === 'kg') {
                    qty = parseFloat(m[1]);
                } else if (item.dynamic === 'gr') {
                    qty = Math.round((parseFloat(m[1]) / 1000) * 100) / 100;
                } else {
                    qty = item.val;
                }
                cleaned = cleaned.replace(item.rgx, '').trim();
                break;
            }
        }

        cleaned = cleaned.replace(/^(?:kilogramos?|kilos?|kgs?|gramos?|grs?|gr|k|g)\s+/i, '').trim();
        cleaned = cleaned.replace(/^(?:de\s+la|de\s+el|del|de)\s+/i, '').trim();
        return { qty: qty, term: cleaned };
    }

    /**
     * Limpia modismos, saludos y fórmulas de cortesía típicas de una carnicería mexicana
     * para extraer el corte o producto exacto que se desea buscar.
     */
    function cleanVoiceSearchQuery(rawText) {
        if (!rawText) return '';
        var text = rawText.trim();

        // Eliminar signos de puntuación innecesarios
        text = text.replace(/[¿?¡!.,;:"]/g, ' ');

        var prefixes = [
            /^(?:hola|buenos\s+dias|buenas\s+tardes|buenas|a\s+ver)\s+/i,
            /^(?:me\s+das|me\s+da|deme|dame|quiero|quisiera|deseo|busco|tienes|tienen|hay|véndame|vendame|póngame|pongame|ponme|échame|echame)\s+/i
        ];

        var suffixes = [
            /\s+(?:por\s+favor|gracias|muchas\s+gracias)$/i,
            /\s+(?:para\s+llevar|para\s+el\s+domingo|para\s+hoy)$/i
        ];

        var cleaned = text;
        for (var i = 0; i < 2; i++) {
            var prev = cleaned;
            prefixes.forEach(function (rgx) {
                cleaned = cleaned.replace(rgx, '');
            });
            suffixes.forEach(function (rgx) {
                cleaned = cleaned.replace(rgx, '');
            });
            cleaned = cleaned.trim();
            if (prev === cleaned) break;
        }

        // Extracción inteligente de cantidad y producto
        var extracted = extractVoiceQuantity(cleaned);
        if (extracted.qty !== null) {
            currentDetectedQty = extracted.qty;
            cleaned = extracted.term;
        } else {
            currentDetectedQty = null;
        }

        if (cleaned.length < 2) {
            return text.trim();
        }
        return cleaned;
    }

    function openVoiceModal() {
        if (!voiceModal) {
            var modalEl = document.getElementById('voiceSearchModal');
            if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                voiceModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true });
            }
        }
        if (voiceModal) {
            voiceModal.show();
        }

        resetVoiceModalState();

        if (!SpeechRecognition) {
            showVoiceAlert('Tu navegador no cuenta con soporte nativo de voz. Te invitamos a utilizar el buscador escrito.', 'warning');
            return;
        }

        // Iniciar escucha automáticamente al abrir el modal tras un breve instante
        setTimeout(function () {
            startListening();
        }, 350);
    }

    function resetVoiceModalState() {
        currentRawTranscript = '';
        currentCleanedQuery = '';
        currentDetectedQty = null;
        searchTriggered = false;
        var statusEl = document.getElementById('voiceStatusText');
        var displayEl = document.getElementById('voiceTranscriptDisplay');
        var cleanedPreview = document.getElementById('voiceCleanedPreview');
        var alertEl = document.getElementById('voiceAlertBox');
        var submitBtn = document.getElementById('voiceModalSubmitBtn');
        var micBtn = document.getElementById('voiceModalMicBtn');

        if (statusEl) statusEl.textContent = 'Presione el micrófono y diga lo que busca';
        if (displayEl) {
            displayEl.innerHTML = '<span class="text-muted fst-italic">"Diga por ejemplo: Un kilo de arrachera para asar"</span>';
        }
        if (cleanedPreview) cleanedPreview.classList.add('d-none');
        if (alertEl) alertEl.classList.add('d-none');
        if (submitBtn) submitBtn.disabled = true;
        if (micBtn) micBtn.classList.remove('is-listening');
    }

    function triggerAutoSearch() {
        if (searchTriggered) return;
        var query = currentCleanedQuery || currentRawTranscript;
        if (!query || query.trim() === '') return;

        searchTriggered = true;
        if (silenceTimer) {
            clearTimeout(silenceTimer);
            silenceTimer = null;
        }

        var statusEl = document.getElementById('voiceStatusText');
        if (statusEl) statusEl.textContent = '¡Listo! Buscando ' + query + '...';

        setTimeout(function () {
            stopListening(false);
            executeSearch(query.trim());
        }, 300);
    }

    function startListening() {
        if (!SpeechRecognition) {
            showVoiceAlert('Reconocimiento de voz no soportado en este navegador.', 'warning');
            return;
        }

        if (isListening) return;

        try {
            recognition = new SpeechRecognition();
            recognition.lang = 'es-MX';
            recognition.continuous = false;
            recognition.interimResults = true;
            recognition.maxAlternatives = 1;

            recognition.onstart = function () {
                isListening = true;
                setMicVisualState(true);
                var statusEl = document.getElementById('voiceStatusText');
                if (statusEl) statusEl.textContent = '🎙️ Escuchando... Hable claro a su micrófono';
                hideVoiceAlert();
            };

            recognition.onresult = function (event) {
                var interim = '';
                for (var i = event.resultIndex; i < event.results.length; ++i) {
                    var text = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        currentRawTranscript = text;
                    } else {
                        interim += text;
                    }
                }

                var spokenText = currentRawTranscript || interim;
                updateTranscriptDisplay(spokenText);

                if (spokenText.trim() !== '') {
                    currentCleanedQuery = cleanVoiceSearchQuery(spokenText);
                    updateCleanedPreview(currentCleanedQuery);

                    var submitBtn = document.getElementById('voiceModalSubmitBtn');
                    if (submitBtn) submitBtn.disabled = false;

                    // Reiniciar temporizador de silencio para búsqueda automática
                    if (silenceTimer) clearTimeout(silenceTimer);
                    silenceTimer = setTimeout(function () {
                        if (!searchTriggered && currentCleanedQuery) {
                            triggerAutoSearch();
                        }
                    }, 1100);
                }
            };

            recognition.onerror = function (event) {
                isListening = false;
                setMicVisualState(false);
                var statusEl = document.getElementById('voiceStatusText');
                if (statusEl) statusEl.textContent = 'No logramos captar el audio';

                if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    showVoiceAlert('Permiso de micrófono denegado. Permite el acceso al micrófono en tu navegador.', 'danger');
                } else if (event.error === 'no-speech') {
                    showVoiceAlert('No detectamos sonido. Presiona el micrófono e intenta de nuevo.', 'warning');
                } else {
                    showVoiceAlert('Ocurrió un detalle al captar la voz: ' + event.error, 'warning');
                }
            };

            recognition.onend = function () {
                isListening = false;
                setMicVisualState(false);
                if (currentCleanedQuery && !searchTriggered) {
                    triggerAutoSearch();
                }
            };

            recognition.start();
        } catch (err) {
            isListening = false;
            setMicVisualState(false);
            showVoiceAlert('Error al iniciar el micrófono: ' + err.message, 'danger');
        }
    }

    function stopListening(triggerSearchIfReady) {
        if (silenceTimer) {
            clearTimeout(silenceTimer);
            silenceTimer = null;
        }
        if (recognition) {
            try {
                recognition.stop();
            } catch (e) {}
        }
        isListening = false;
        setMicVisualState(false);

        if (triggerSearchIfReady) {
            var query = currentCleanedQuery || currentRawTranscript;
            if (query && query.trim() !== '') {
                executeSearch(query.trim());
            }
        }
    }

    function setMicVisualState(listening) {
        var micBtn = document.getElementById('voiceModalMicBtn');
        var badge = document.getElementById('voiceProcessingBadge');
        if (micBtn) {
            if (listening) {
                micBtn.classList.add('is-listening');
            } else {
                micBtn.classList.remove('is-listening');
            }
        }
        if (badge) {
            if (listening) {
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
    }

    function updateTranscriptDisplay(text) {
        var displayEl = document.getElementById('voiceTranscriptDisplay');
        if (displayEl) {
            displayEl.textContent = '"' + text + '"';
        }
    }

    function updateCleanedPreview(cleaned) {
        var previewCard = document.getElementById('voiceCleanedPreview');
        var targetText = document.getElementById('voiceCleanedText');
        if (previewCard && targetText) {
            if (cleaned) {
                var previewLabel = cleaned;
                if (currentDetectedQty !== null && currentDetectedQty > 0) {
                    previewLabel += ' (' + currentDetectedQty + ' kg)';
                }
                targetText.textContent = previewLabel;
                previewCard.classList.remove('d-none');
            } else {
                previewCard.classList.add('d-none');
            }
        }
    }

    function showVoiceAlert(msg, type) {
        var alertEl = document.getElementById('voiceAlertBox');
        if (alertEl) {
            alertEl.className = 'alert alert-' + (type || 'warning') + ' py-2 px-3 small mb-3';
            alertEl.textContent = msg;
            alertEl.classList.remove('d-none');
        }
    }

    function hideVoiceAlert() {
        var alertEl = document.getElementById('voiceAlertBox');
        if (alertEl) {
            alertEl.classList.add('d-none');
        }
    }

    /**
     * Respuesta hablada del carnicero mediante SpeechSynthesis
     */
    function speakButcherResponse(message, callback) {
        if (!('speechSynthesis' in window)) {
            if (callback) callback();
            return;
        }

        try {
            window.speechSynthesis.cancel();
            var utterance = new SpeechSynthesisUtterance(message);
            utterance.lang = 'es-MX';
            utterance.rate = 1.0;
            utterance.pitch = 0.95;

            var voices = window.speechSynthesis.getVoices();
            var esVoice = voices.find(function (v) { return v.lang === 'es-MX'; }) ||
                          voices.find(function (v) { return v.lang && v.lang.startsWith('es'); });
            if (esVoice) utterance.voice = esVoice;

            var finished = false;
            var handleDone = function () {
                if (!finished) {
                    finished = true;
                    if (callback) callback();
                }
            };

            utterance.onend = handleDone;
            utterance.onerror = handleDone;

            // En móviles si tarda mucho, no bloquear la navegación
            setTimeout(handleDone, 2200);

            window.speechSynthesis.speak(utterance);
        } catch (e) {
            if (callback) callback();
        }
    }

    /**
     * Ejecuta la búsqueda colocando el término en los inputs y disparando la búsqueda AJAX o recarga
     */
    function executeSearch(query) {
        if (!query) return;

        if (voiceModal) {
            voiceModal.hide();
        }

        // Colocar el término en todos los inputs visibles de búsqueda
        var inputs = document.querySelectorAll('input[name="buscar"]');
        inputs.forEach(function (inp) {
            inp.value = query;
        });

        // Frases amables con tono de carnicería
        var qtyPrefix = (currentDetectedQty !== null && currentDetectedQty > 0) ? (currentDetectedQty + ' kg de ') : '';
        var butcherPhrases = [
            '¡Enseguida marchante! Buscando ' + qtyPrefix + query,
            '¡Con gusto! Le muestro ' + qtyPrefix + query,
            'Buscando ' + qtyPrefix + query + ' fresco para usted'
        ];
        var phrase = butcherPhrases[Math.floor(Math.random() * butcherPhrases.length)];

        var audioToggle = document.getElementById('voiceAudioFeedbackToggle');
        var playAudio = audioToggle ? audioToggle.checked : true;

        var targetUrl = 'index.php?buscar=' + encodeURIComponent(query);
        if (currentDetectedQty !== null && currentDetectedQty > 0) {
            targetUrl += '&qty=' + encodeURIComponent(currentDetectedQty);
        }
        targetUrl += '#catalogo';

        var doSearch = function () {
            if (window.canoUpdateCatalog && document.getElementById('catalog-main-content')) {
                window.canoUpdateCatalog(targetUrl);
            } else {
                window.location.href = targetUrl;
            }
        };

        if (playAudio) {
            speakButcherResponse(phrase);
            doSearch();
        } else {
            doSearch();
        }
    }

    // Exponer función global para abrir modal por si se desea llamar externamente
    window.openCanoVoiceSearch = openVoiceModal;

})();
