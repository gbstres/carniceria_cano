<section class="catalog-section py-4" id="producto-detalle">
    <div class="container">
        <?php if (!empty($feedback['message'])): ?>
            <div class="alert alert-<?php echo storefront_escape($feedback['type']); ?> storefront-alert alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php echo storefront_escape($feedback['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if ($product === null): ?>
            <div class="empty-state-card text-center py-5 bg-white rounded-4 shadow-sm p-4">
                <div class="empty-icon mb-3"><i class="bi bi-exclamation-triangle-fill text-warning display-4"></i></div>
                <h2 class="fw-bold">Producto no encontrado</h2>
                <p class="text-muted">El producto solicitado no está disponible o ya no existe en el catálogo.</p>
                <a href="index.php" class="btn btn-primary-store px-4 py-2 rounded-3 mt-2">
                    <i class="bi bi-arrow-left me-1"></i> Volver a la Tienda
                </a>
            </div>
        <?php else: ?>
            <!-- Navegación y retorno rápido -->
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 py-1 small">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>Catálogo</a></li>
                        <li class="breadcrumb-item"><a href="index.php?categoria=<?php echo urlencode($product['categoria']); ?>" class="text-decoration-none text-muted"><?php echo storefront_escape($product['categoria']); ?></a></li>
                        <li class="breadcrumb-item active text-dark fw-bold" aria-current="page"><?php echo storefront_escape($product['descripcion']); ?></li>
                    </ol>
                </nav>
                <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center">
                    <i class="bi bi-arrow-left me-1"></i> Regresar
                </a>
            </div>

            <div class="product-detail-shell">
                <div class="product-detail-gallery">
                    <div class="product-detail-main-image rounded-4 overflow-hidden shadow-sm border position-relative" id="mainImageContainer">
                        <img id="mainProductImage" src="<?php echo storefront_escape($product['image_large']); ?>" alt="<?php echo storefront_escape($product['image_alt']); ?>" class="product-detail-image w-100" fetchpriority="high">
                        
                        <!-- Indicador visual de Lupa interactiva (Desktop) -->
                        <div class="product-gallery-zoom-badge d-none d-md-inline-flex" id="zoomHintBadge">
                            <i class="bi bi-search"></i> Pasa el mouse para ampliar
                        </div>

                        <?php if (count($productGallery) > 1): ?>
                            <!-- Flechas de navegación sobre la foto principal -->
                            <button type="button" class="product-gallery-nav-btn product-gallery-prev" id="galleryPrevBtn" aria-label="Foto anterior">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button type="button" class="product-gallery-nav-btn product-gallery-next" id="galleryNextBtn" aria-label="Foto siguiente">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                            
                            <!-- Indicador de foto actual (1 / N) -->
                            <div class="product-gallery-badge badge text-white position-absolute top-0 end-0 m-3 px-3 py-1 rounded-pill">
                                <span id="galleryCurrentIndex">1</span> / <?php echo count($productGallery); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Botón para ver en pantalla completa / zoom -->
                        <button type="button" class="product-gallery-zoom-btn btn rounded-circle shadow position-absolute bottom-0 end-0 m-3" id="btnOpenLightbox" data-bs-toggle="modal" data-bs-target="#galleryLightboxModal" title="Ampliar imagen / Pantalla completa" aria-label="Ampliar imagen">
                            <i class="bi bi-arrows-fullscreen"></i>
                        </button>
                    </div>

                    <?php if (count($productGallery) > 1): ?>
                        <!-- Carrusel de miniaturas inferiores -->
                        <div class="product-detail-thumbs-container mt-3">
                            <button type="button" class="thumbs-scroll-btn thumbs-scroll-prev d-none d-md-flex" id="thumbsScrollPrev" aria-label="Desplazar miniaturas hacia la izquierda">
                                <i class="bi bi-chevron-left"></i>
                            </button>

                            <div class="product-detail-thumbs-track" id="thumbsTrack">
                                <?php foreach ($productGallery as $idx => $image): ?>
                                    <button type="button" class="product-detail-thumb <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>" aria-label="Ver fotografía <?php echo $idx + 1; ?>">
                                        <img src="<?php echo storefront_escape($image['small']); ?>" alt="<?php echo storefront_escape($image['alt']); ?>" class="w-100 h-100 object-fit-cover" loading="lazy" decoding="async">
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <button type="button" class="thumbs-scroll-btn thumbs-scroll-next d-none d-md-flex" id="thumbsScrollNext" aria-label="Desplazar miniaturas hacia la derecha">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="product-detail-card p-4 bg-white rounded-4 shadow-sm border">
                    <span class="eyebrow-badge mb-2"><i class="bi bi-tag-fill me-1"></i> <?php echo storefront_escape($product['categoria']); ?></span>
                    <h2 class="fw-bold text-dark mb-2"><?php echo storefront_escape($product['descripcion']); ?></h2>
                    <p class="product-detail-code text-muted mb-3"><i class="bi bi-barcode me-1"></i> Código: <?php echo storefront_escape($product['codigo']); ?></p>

                    <div class="product-detail-price-row d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-3">
                        <div>
                            <span class="text-muted small d-block">Precio por unidad/kg:</span>
                            <strong class="h2 fw-bold text-danger mb-0"><?php echo storefront_escape(storefront_money($product['precio_venta'])); ?></strong>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success fs-6 px-3 py-2 rounded-pill">
                            <i class="bi bi-check-circle-fill me-1"></i> <?php echo storefront_escape(storefront_stock_label($product['almacen'])); ?>
                        </span>
                    </div>

                    <div class="product-detail-meta row g-3 mb-4">
                        <div class="col-6">
                            <div class="p-3 border rounded-3 bg-white">
                                <span class="product-detail-meta-label text-muted small text-uppercase fw-semibold d-block mb-1">Disponibilidad Aproximada</span>
                                <p class="fw-bold text-dark m-0"><?php echo storefront_escape(number_format((float) $product['almacen'], 2)); ?> kg/pza</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded-3 bg-white">
                                <span class="product-detail-meta-label text-muted small text-uppercase fw-semibold d-block mb-1">Categoría</span>
                                <p class="fw-bold text-dark m-0"><?php echo storefront_escape($product['categoria']); ?></p>
                            </div>
                        </div>
                    </div>

                    <form method="post" class="product-form product-detail-form mb-4">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_code" value="<?php echo storefront_escape($product['codigo']); ?>">
                        
                        <label class="form-label fw-bold small text-dark mb-1" for="qty-product-detail">Cantidad deseada:</label>
                        <div class="quantity-touch-stepper mb-3">
                            <button type="button" class="btn-step-touch" onclick="stepQtyDetail(-0.25)">-</button>
                            <input id="qty-product-detail" type="number" name="quantity" min="0.25" step="0.25" value="1" class="form-control qty-touch-input text-center fw-bold">
                            <button type="button" class="btn-step-touch" onclick="stepQtyDetail(0.25)">+</button>
                        </div>
                        <button type="submit" class="btn btn-danger w-100 py-3 rounded-3 fw-bold shadow-sm fs-6">
                            <i class="bi bi-cart-plus-fill me-1"></i> Agregar al Carrito
                        </button>
                    </form>

                    <div class="product-detail-actions d-flex gap-2">
                        <a href="index.php#catalogo" class="btn btn-outline-secondary rounded-3 w-50 py-2">
                            <i class="bi bi-arrow-left me-1"></i> Volver a la Tienda
                        </a>
                        <a href="pedido.php" class="btn btn-outline-danger rounded-3 w-50 py-2">
                            <i class="bi bi-bag-check me-1"></i> Ir al Pedido
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modal Lightbox para Pantalla Completa (Zoom) -->
<div class="modal fade" id="galleryLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content lightbox-modal-content text-white rounded-4 overflow-hidden position-relative">
            <!-- Botón de Cierre Superior Derecho (Touch-friendly 44x44px con z-index 1050) -->
            <button type="button" class="lightbox-close-btn" id="btnCloseLightbox" data-bs-dismiss="modal" aria-label="Cerrar ventana">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="modal-body p-0 position-relative d-flex align-items-center justify-content-center" style="min-height: 65vh; max-height: 88vh;">
                <img id="lightboxImage" src="<?php echo storefront_escape($product['image_large'] ?? ''); ?>" alt="<?php echo storefront_escape($product['image_alt'] ?? ''); ?>" class="img-fluid rounded-3" style="max-height: 85vh; max-width: 95%; object-fit: contain;">

                <?php if (count($productGallery) > 1): ?>
                    <button type="button" class="lightbox-nav-btn lightbox-prev" id="lightboxPrevBtn" aria-label="Anterior">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button type="button" class="lightbox-nav-btn lightbox-next" id="lightboxNextBtn" aria-label="Siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <div class="badge bg-dark bg-opacity-75 text-white position-absolute bottom-0 start-50 translate-middle-x mb-3 px-3 py-1 rounded-pill">
                        <span id="lightboxCurrentIndex">1</span> / <?php echo count($productGallery); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function stepQtyDetail(step) {
    var el = document.getElementById('qty-product-detail');
    if (!el) return;
    var val = parseFloat(el.value) || 1;
    val = Math.max(0.25, val + step);
    el.value = val.toFixed(2).replace(/\.00$/, '');
}

(function() {
    var gallery = <?php echo json_encode(array_values($productGallery), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?> || [];
    var currentIndex = 0;
    var container = document.getElementById('mainImageContainer');
    var mainImg = document.getElementById('mainProductImage');
    var zoomBadge = document.getElementById('zoomHintBadge');
    var counterEl = document.getElementById('galleryCurrentIndex');
    var thumbsTrack = document.getElementById('thumbsTrack');
    var thumbs = document.querySelectorAll('.product-detail-thumb');
    
    var lightboxModalEl = document.getElementById('galleryLightboxModal');
    var lightboxImg = document.getElementById('lightboxImage');
    var lightboxCounter = document.getElementById('lightboxCurrentIndex');
    var btnLightbox = document.getElementById('btnOpenLightbox');

    function getBootstrapModal() {
        if (!lightboxModalEl) return null;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            return bootstrap.Modal.getOrCreateInstance(lightboxModalEl);
        }
        return null;
    }

    function openLightbox() {
        if (!gallery.length) return;
        updateSlide(currentIndex);

        // Reset hover zoom en la foto principal al abrir lightbox
        if (container && mainImg) {
            container.classList.remove('is-zoomed');
            mainImg.style.transform = 'scale(1)';
            mainImg.style.transformOrigin = 'center center';
        }

        var bsModal = getBootstrapModal();
        if (bsModal) {
            bsModal.show();
        } else if (lightboxModalEl) {
            // Fallback en caso de que bootstrap aún no esté disponible
            lightboxModalEl.classList.add('show');
            lightboxModalEl.style.display = 'block';
            document.body.classList.add('modal-open');
            if (!document.getElementById('lightboxCustomBackdrop')) {
                var backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'lightboxCustomBackdrop';
                backdrop.addEventListener('click', closeLightboxModal);
                document.body.appendChild(backdrop);
            }
        }
    }

    function closeLightboxModal() {
        var bsModal = getBootstrapModal();
        if (bsModal) {
            try {
                bsModal.hide();
            } catch (err) {}
        }
        if (lightboxModalEl) {
            lightboxModalEl.classList.remove('show');
            lightboxModalEl.style.display = 'none';
            lightboxModalEl.setAttribute('aria-hidden', 'true');
            lightboxModalEl.removeAttribute('aria-modal');
        }
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');

        // Remover cualquier backdrop presente
        var backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(function(b) {
            b.remove();
        });
    }

    // Vincular cierre en botones con clase o atributo de cierre
    if (lightboxModalEl) {
        var dismissBtns = lightboxModalEl.querySelectorAll('.lightbox-close-btn, [data-bs-dismiss="modal"]');
        dismissBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeLightboxModal();
            });
            btn.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeLightboxModal();
            });
        });

        // Clic en el fondo oscuro exterior para cerrar
        lightboxModalEl.addEventListener('click', function(e) {
            if (e.target === lightboxModalEl || e.target.classList.contains('modal-dialog')) {
                closeLightboxModal();
            }
        });

        // Clic en el área oscura de modal-body fuera de la foto para cerrar
        var modalBody = lightboxModalEl.querySelector('.modal-body');
        if (modalBody) {
            modalBody.addEventListener('click', function(e) {
                if (e.target === modalBody) {
                    closeLightboxModal();
                }
            });
        }

        // Sincronizar evento de cierre de Bootstrap
        lightboxModalEl.addEventListener('hidden.bs.modal', function() {
            closeLightboxModal();
        });
    }

    function updateSlide(index) {
        if (!gallery.length) return;
        if (index < 0) {
            index = gallery.length - 1;
        } else if (index >= gallery.length) {
            index = 0;
        }
        currentIndex = index;

        // Actualizar imagen principal en el frame de arriba
        if (mainImg) {
            mainImg.style.opacity = '0.35';
            setTimeout(function() {
                mainImg.src = gallery[currentIndex].large;
                mainImg.alt = gallery[currentIndex].alt;
                mainImg.style.opacity = '1';
            }, 80);
        }

        // Reset hover zoom si estaba activo
        if (container && mainImg) {
            container.classList.remove('is-zoomed');
            mainImg.style.transform = 'scale(1)';
            mainImg.style.transformOrigin = 'center center';
        }

        // Actualizar contador
        if (counterEl) {
            counterEl.textContent = (currentIndex + 1);
        }

        // Actualizar miniaturas inferiores
        thumbs.forEach(function(t, i) {
            if (i === currentIndex) {
                t.classList.add('active');
                t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } else {
                t.classList.remove('active');
            }
        });

        // Actualizar Lightbox si está abierto
        if (lightboxImg && gallery[currentIndex]) {
            lightboxImg.src = gallery[currentIndex].large;
            lightboxImg.alt = gallery[currentIndex].alt;
            lightboxImg.style.transform = 'scale(1)';
            lightboxImg.style.cursor = 'zoom-in';
        }
        if (lightboxCounter) {
            lightboxCounter.textContent = (currentIndex + 1);
        }
    }

    // Lupa interactiva al pasar el mouse sobre la foto principal (Desktop)
    if (container && mainImg) {
        var hasHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

        if (hasHover) {
            container.addEventListener('mouseenter', function() {
                container.classList.add('is-zoomed');
                mainImg.style.transform = 'scale(2.3)';
                if (zoomBadge) {
                    zoomBadge.innerHTML = '<i class="bi bi-zoom-in"></i> Lupa activa · Clic para pantalla completa';
                }
            });

            container.addEventListener('mousemove', function(e) {
                var rect = container.getBoundingClientRect();
                if (!rect.width || !rect.height) return;
                var x = ((e.clientX - rect.left) / rect.width) * 100;
                var y = ((e.clientY - rect.top) / rect.height) * 100;
                x = Math.max(0, Math.min(100, x));
                y = Math.max(0, Math.min(100, y));
                mainImg.style.transformOrigin = x.toFixed(1) + '% ' + y.toFixed(1) + '%';
            });

            container.addEventListener('mouseleave', function() {
                container.classList.remove('is-zoomed');
                mainImg.style.transform = 'scale(1)';
                mainImg.style.transformOrigin = 'center center';
                if (zoomBadge) {
                    zoomBadge.innerHTML = '<i class="bi bi-search"></i> Pasa el mouse para ampliar';
                }
            });
        }
    }

    // Botones de navegación en foto principal
    var prevBtn = document.getElementById('galleryPrevBtn');
    var nextBtn = document.getElementById('galleryNextBtn');
    if (prevBtn) {
        prevBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            updateSlide(currentIndex - 1);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            updateSlide(currentIndex + 1);
        });
    }

    // Clic en miniaturas inferiores para mostrar en el frame de arriba
    thumbs.forEach(function(thumb) {
        thumb.addEventListener('click', function() {
            var idx = parseInt(this.getAttribute('data-index'), 10);
            updateSlide(idx);
        });
    });

    // Desplazamiento lateral de miniaturas con flechas en desktop
    var thumbsPrev = document.getElementById('thumbsScrollPrev');
    var thumbsNext = document.getElementById('thumbsScrollNext');
    if (thumbsPrev && thumbsTrack) {
        thumbsPrev.addEventListener('click', function() {
            thumbsTrack.scrollBy({ left: -160, behavior: 'smooth' });
        });
    }
    if (thumbsNext && thumbsTrack) {
        thumbsNext.addEventListener('click', function() {
            thumbsTrack.scrollBy({ left: 160, behavior: 'smooth' });
        });
    }

    // Deslizamiento táctil con el dedo en celular (Touch Swipe)
    var container = document.getElementById('mainImageContainer');
    if (container && gallery.length > 1) {
        var touchStartX = 0;
        var touchStartY = 0;
        container.addEventListener('touchstart', function(e) {
            if (e.touches.length === 1) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            }
        }, { passive: true });

        container.addEventListener('touchend', function(e) {
            if (e.changedTouches.length === 1) {
                var touchEndX = e.changedTouches[0].clientX;
                var touchEndY = e.changedTouches[0].clientY;
                var deltaX = touchEndX - touchStartX;
                var deltaY = touchEndY - touchStartY;

                // Solo si el deslizamiento es predominantemente horizontal
                if (Math.abs(deltaX) > 35 && Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
                    if (deltaX < 0) {
                        updateSlide(currentIndex + 1); // Swipe hacia la izquierda -> siguiente
                    } else {
                        updateSlide(currentIndex - 1); // Swipe hacia la derecha -> anterior
                    }
                }
            }
        }, { passive: true });
    }

    // Abrir Lightbox al hacer clic en botón de zoom o en la foto principal
    if (btnLightbox) {
        btnLightbox.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openLightbox();
        });
    }
    if (mainImg) {
        mainImg.addEventListener('click', function() {
            openLightbox();
        });
    }

    // Navegación dentro del Lightbox
    var lbPrev = document.getElementById('lightboxPrevBtn');
    var lbNext = document.getElementById('lightboxNextBtn');
    if (lbPrev) {
        lbPrev.addEventListener('click', function() {
            updateSlide(currentIndex - 1);
        });
    }
    if (lbNext) {
        lbNext.addEventListener('click', function() {
            updateSlide(currentIndex + 1);
        });
    }

    // Zoom secundario al hacer clic sobre la foto en Lightbox
    if (lightboxImg) {
        var isLbZoomed = false;
        lightboxImg.addEventListener('click', function() {
            isLbZoomed = !isLbZoomed;
            if (isLbZoomed) {
                this.style.transform = 'scale(1.6)';
                this.style.cursor = 'zoom-out';
            } else {
                this.style.transform = 'scale(1)';
                this.style.cursor = 'zoom-in';
            }
        });
    }

    // Navegación por teclado (flechas izquierda/derecha y Escape)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightboxModal();
        } else if (gallery.length > 1) {
            if (e.key === 'ArrowLeft') {
                updateSlide(currentIndex - 1);
            } else if (e.key === 'ArrowRight') {
                updateSlide(currentIndex + 1);
            }
        }
    });
})();
</script>
