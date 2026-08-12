// Inject mobile nav immediately to prevent FOUC / flicker on page transitions
(function() {
    if (window.innerWidth > 768) return;
    if (document.getElementById('mobileBottomNav')) return;
    const currentPath = window.location.pathname.toLowerCase();
    const isHome = currentPath.includes('index.php') || currentPath.endsWith('/') || currentPath.endsWith('digiprox24');
    const isCat = currentPath.includes('categories.php');
    const isProd = currentPath.includes('products.php') && !isCat;
    const isCart = currentPath.includes('cart.php');
    
    // We try to grab the cart count if the DOM is already parsed above this script
    let initialCartCount = '0';
    try { initialCartCount = document.querySelector('.header-actions .cart-count')?.innerText || '0'; } catch(e){}

    const nav = document.createElement('div');
    nav.id = 'mobileBottomNav';
    nav.className = 'mobile-bottom-nav no-print';
    nav.innerHTML = `
        <a href="index.php" class="mobile-nav-item ${isHome ? 'active' : ''}">
            <span class="mobile-nav-icon">🏠</span>
            <span class="mobile-nav-label">Home</span>
        </a>
        <a href="categories.php" class="mobile-nav-item ${isCat ? 'active' : ''}">
            <span class="mobile-nav-icon">📂</span>
            <span class="mobile-nav-label">Categories</span>
        </a>
        <a href="products.php" class="mobile-nav-item ${isProd ? 'active' : ''}">
            <span class="mobile-nav-icon">🛍️</span>
            <span class="mobile-nav-label">Shop</span>
        </a>
        <a href="cart.php" class="mobile-nav-item ${isCart ? 'active' : ''}">
            <span class="mobile-nav-icon" style="position:relative;">
                🛒
                <span class="cart-count mobile-cart-badge">${initialCartCount}</span>
            </span>
            <span class="mobile-nav-label">Cart</span>
        </a>
    `;
    if (document.body) {
        document.body.appendChild(nav);
    } else {
        document.addEventListener('DOMContentLoaded', () => document.body.appendChild(nav));
    }
})();

document.addEventListener('DOMContentLoaded', () => {
    // ── Sidebar Shopping Cart Drawer Implementation ──
    const injectCartDrawer = () => {
        if (document.getElementById('cartDrawerOverlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'cartDrawerOverlay';
        overlay.className = 'cart-drawer-overlay';
        overlay.onclick = closeCartDrawer;

        const drawer = document.createElement('div');
        drawer.id = 'cartDrawer';
        drawer.className = 'cart-drawer';
        drawer.innerHTML = `
            <div class="cart-drawer-header">
                <h3>Shopping cart</h3>
                <button class="cart-drawer-close" onclick="closeCartDrawer()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Close
                </button>
            </div>
            <div class="cart-drawer-items" id="cartDrawerItems">
                <!-- Populated dynamically -->
            </div>
            <div class="cart-drawer-footer">
                <div class="cart-drawer-delivery-row" id="cartDrawerDeliveryRow" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; font-weight: 600; color: var(--text-muted); font-size: 0.95rem;">
                    <span>Delivery Fee:</span>
                    <span id="cartDrawerDeliveryVal" style="color: var(--text-main); font-weight: 700;">Rs. 0</span>
                </div>
                <div class="cart-drawer-subtotal-row">
                    <span class="cart-drawer-subtotal-label">Subtotal:</span>
                    <span class="cart-drawer-subtotal-val" id="cartDrawerSubtotal">Rs. 0</span>
                </div>
                <a href="cart.php" class="cart-drawer-btn cart-drawer-btn-view">View Cart</a>
                <a href="checkout.php" class="cart-drawer-btn cart-drawer-btn-checkout">Checkout</a>
            </div>
        `;

        document.body.appendChild(overlay);
        document.body.appendChild(drawer);
    };

    const openCartDrawer = () => {
        injectCartDrawer();
        document.getElementById('cartDrawerOverlay').classList.add('active');
        document.getElementById('cartDrawer').classList.add('active');
        document.body.style.overflow = 'hidden';
        updateCartDrawer();
    };

    const closeCartDrawer = () => {
        const overlay = document.getElementById('cartDrawerOverlay');
        const drawer = document.getElementById('cartDrawer');
        if (overlay && drawer) {
            overlay.classList.remove('active');
            drawer.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    };

    const updateCartDrawer = () => {
        const itemsContainer = document.getElementById('cartDrawerItems');
        if (!itemsContainer) return;

        itemsContainer.innerHTML = '<div style="text-align:center; padding: 2rem; color: #64748b;">Loading cart items...</div>';

        fetch('get_cart_ajax.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.cart-count').forEach(el => el.innerText = data.cart_count);
                    
                    // Dynamically update or inject footer breakdown
                    const drawer = document.getElementById('cartDrawer');
                    let footer = drawer ? drawer.querySelector('.cart-drawer-footer') : null;
                    const itemsTotalText = 'Rs. ' + Math.round(data.items_subtotal || 0).toLocaleString('en-US');
                    const deliveryText = data.delivery_fee === 0 ? 'Free' : 'Rs. ' + Math.round(data.delivery_fee).toLocaleString('en-US');
                    const subtotalText = 'Rs. ' + Math.round(data.subtotal).toLocaleString('en-US');

                    if (footer) {
                        footer.innerHTML = `
                            <div class="cart-drawer-item-total-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; font-weight: 500; color: var(--text-muted); font-size: 0.95rem;">
                                <span>Item Total:</span>
                                <span style="color: var(--text-main); font-weight: 600;">${itemsTotalText}</span>
                            </div>
                            <div class="cart-drawer-delivery-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; font-weight: 500; color: var(--text-muted); font-size: 0.95rem;">
                                <span>Delivery Fee:</span>
                                <span style="color: var(--text-main); font-weight: 600;">${deliveryText}</span>
                            </div>
                            <div class="cart-drawer-subtotal-row">
                                <span class="cart-drawer-subtotal-label">Subtotal:</span>
                                <span class="cart-drawer-subtotal-val">${subtotalText}</span>
                            </div>
                            <a href="cart.php" class="cart-drawer-btn cart-drawer-btn-view">View Cart</a>
                            <a href="checkout.php" class="cart-drawer-btn cart-drawer-btn-checkout">Checkout</a>
                        `;
                    }

                    if (data.items.length === 0) {
                        itemsContainer.innerHTML = `
                            <div style="text-align:center; padding: 4rem 1rem; color: #64748b; display:flex; flex-direction:column; align-items:center; gap: 1rem;">
                                <span style="font-size: 3rem;">🛒</span>
                                <p style="font-weight:600;">Your cart is empty</p>
                                <button onclick="closeCartDrawer()" class="btn-primary" style="padding:0.6rem 1.5rem; font-size:0.85rem; margin-top:0.5rem;">Continue Shopping</button>
                            </div>
                        `;
                        return;
                    }

                    itemsContainer.innerHTML = '';
                    data.items.forEach(item => {
                        const row = document.createElement('div');
                        row.className = 'cart-drawer-item';
                        
                        let displayImage = item.image;
                        if (!displayImage.startsWith('http') && !displayImage.startsWith('uploads/')) {
                            // Try local path resolver fallback
                        }
                        
                        let shippingBadge = item.shipping_fee === 0
                            ? `<div style="color: #10b981; font-weight: 600; font-size: 0.78rem; margin-top: 2px; margin-bottom: 6px;">🚚 Free Delivery</div>`
                            : `<div style="color: rgba(255, 255, 255, 0.65); font-weight: 500; font-size: 0.78rem; margin-top: 2px; margin-bottom: 6px;">🚚 Delivery: Rs. ${Math.round(item.shipping_fee).toLocaleString('en-US')}</div>`;

                        row.innerHTML = `
                            <img src="${displayImage}" alt="${item.name}" class="cart-drawer-item-img">
                            <div class="cart-drawer-item-info">
                                <div class="cart-drawer-item-title">${item.name}</div>
                                ${shippingBadge}
                                <div class="cart-drawer-qty-row">
                                    <div class="cart-drawer-qty-controls">
                                        <button class="cart-drawer-qty-btn" onclick="changeQty('${item.id}', ${item.qty - 1})">-</button>
                                        <input type="text" class="cart-drawer-qty-val" value="${item.qty}" readonly>
                                        <button class="cart-drawer-qty-btn" onclick="changeQty('${item.id}', ${item.qty + 1})">+</button>
                                    </div>
                                    <div class="cart-drawer-price-line">
                                        ${item.qty} × <span class="cart-drawer-price-val">Rs. ${Math.round(item.price).toLocaleString('en-US')}</span>
                                    </div>
                                </div>
                            </div>
                            <button class="cart-drawer-item-remove" onclick="removeDrawerItem('${item.id}')">✕</button>
                        `;
                        itemsContainer.appendChild(row);
                    });
                } else {
                    itemsContainer.innerHTML = '<div style="text-align:center; padding: 2rem; color: #ef4444;">Error loading cart.</div>';
                }
            })
            .catch(err => {
                console.error(err);
                itemsContainer.innerHTML = '<div style="text-align:center; padding: 2rem; color: #ef4444;">Could not load cart.</div>';
            });
    };

    const changeQty = (productId, newQty) => {
        const action = newQty <= 0 ? 'remove' : 'update';
        fetch('cart_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=${action}&product_id=${productId}&qty=${newQty}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateCartDrawer();
            }
        });
    };

    const removeDrawerItem = (productId) => {
        fetch('cart_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=remove&product_id=${productId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateCartDrawer();
            }
        });
    };

    // Expose to window context
    window.openCartDrawer = openCartDrawer;
    window.closeCartDrawer = closeCartDrawer;
    window.updateCartDrawer = updateCartDrawer;
    window.changeQty = changeQty;
    window.removeDrawerItem = removeDrawerItem;

    // Cart Navigation Button Hijack
    document.querySelectorAll('.cart-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openCartDrawer();
        });
    });

    // Add to Cart Buttons Click Handler
    const addCartBtns = document.querySelectorAll('.btn-add-cart');
    addCartBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Check if it's the "View" button in related products
            if (btn.tagName === 'SPAN' && btn.innerText.includes('View')) {
                return;
            }
            e.stopPropagation();
            const productId = btn.getAttribute('data-id');
            if (!productId) return;

            fetch('cart_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=add&product_id=${productId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Instantly slide open the drawer
                    openCartDrawer();
                }
            });
        });
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if(target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Header scroll effect
    const header = document.querySelector('.glass-header');
    if (header) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        }
    }

    // Inject Floating WhatsApp Button
    const whatsappBtn = document.createElement('a');
    whatsappBtn.href = 'https://wa.me/94706756006';
    whatsappBtn.target = '_blank';
    whatsappBtn.className = 'whatsapp-float';
    whatsappBtn.innerHTML = `
        <div class="whatsapp-icon-wrap">
            <svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.792 1.451 5.485.002 9.947-4.437 9.95-9.912.002-2.653-1.03-5.148-2.906-7.027A9.873 9.873 0 0011.997 1.284C6.507 1.284 2.05 5.722 2.046 11.2c-.001 1.761.479 3.483 1.39 5.017L2.45 20.83l4.197-1.101-.001-.005-.002-.008zm11.12-6.504c-.3-.15-1.78-.88-2.05-.98-.27-.1-.47-.15-.67.15-.2.3-.77.98-.95 1.18-.18.2-.35.23-.65.08-1.76-.88-3.15-1.53-4.4-3.67-.33-.57.33-.53.94-1.75.1-.2.05-.38-.02-.53-.07-.15-.67-1.62-.92-2.22-.24-.59-.5-.51-.68-.52-.17-.01-.37-.01-.57-.01-.2 0-.52.08-.8.38-.28.3-1.06 1.04-1.06 2.53 0 1.49 1.08 2.93 1.23 3.13.15.2 2.13 3.25 5.16 4.56.72.31 1.28.5 1.72.64.73.23 1.39.2 1.91.12.58-.09 1.78-.73 2.03-1.43.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35z"/>
            </svg>
        </div>
        <span class="whatsapp-tooltip">Chat on WhatsApp</span>
    `;
    document.body.appendChild(whatsappBtn);

    // Dynamic Mobile Bottom Navigation Bar Injection
    const injectMobileNav = () => {
        if (window.innerWidth > 768) {
            const existingNav = document.getElementById('mobileBottomNav');
            if (existingNav) existingNav.remove();
            return;
        }
        if (document.getElementById('mobileBottomNav')) {
            // Update badge dynamically
            const badge = document.querySelector('.mobile-cart-badge');
            const sourceBadge = document.querySelector('.header-actions .cart-count');
            if (badge && sourceBadge) {
                badge.innerText = sourceBadge.innerText;
            }
            return;
        }
        
        const nav = document.createElement('div');
        nav.id = 'mobileBottomNav';
        nav.className = 'mobile-bottom-nav no-print';
        
        const currentPath = window.location.pathname.toLowerCase();
        const isHome = currentPath.includes('index.php') || currentPath.endsWith('/') || currentPath.endsWith('digiprox24');
        const isCat = currentPath.includes('categories.php');
        const isProd = currentPath.includes('products.php') && !isCat;
        const isCart = currentPath.includes('cart.php');
        
        const initialCartCount = document.querySelector('.header-actions .cart-count')?.innerText || '0';
        
        nav.innerHTML = `
            <a href="index.php" class="mobile-nav-item ${isHome ? 'active' : ''}">
                <span class="mobile-nav-icon">🏠</span>
                <span class="mobile-nav-label">Home</span>
            </a>
            <a href="categories.php" class="mobile-nav-item ${isCat ? 'active' : ''}">
                <span class="mobile-nav-icon">📂</span>
                <span class="mobile-nav-label">Categories</span>
            </a>
            <a href="products.php" class="mobile-nav-item ${isProd ? 'active' : ''}">
                <span class="mobile-nav-icon">🛍️</span>
                <span class="mobile-nav-label">Shop</span>
            </a>
            <a href="cart.php" class="mobile-nav-item ${isCart ? 'active' : ''}">
                <span class="mobile-nav-icon" style="position:relative;">
                    🛒
                    <span class="cart-count mobile-cart-badge">${initialCartCount}</span>
                </span>
                <span class="mobile-nav-label">Cart</span>
            </a>
        `;
        document.body.appendChild(nav);
    };

    const injectMobileDrawer = () => {
        if (document.getElementById('mobileMenuDrawer')) return;

        const overlay = document.createElement('div');
        overlay.id = 'mobileDrawerOverlay';
        overlay.className = 'mobile-drawer-overlay';
        overlay.onclick = toggleMobileMenu;

        const currentPath = window.location.pathname.toLowerCase();
        const isHome = currentPath.includes('index.php') || currentPath.endsWith('/') || currentPath.endsWith('digiprox24');
        const isCat = currentPath.includes('categories.php');
        const isProd = currentPath.includes('products.php') && !isCat;
        const isAbout = currentPath.includes('about.php');
        const isContact = currentPath.includes('contact.php');

        const drawer = document.createElement('div');
        drawer.id = 'mobileMenuDrawer';
        drawer.className = 'mobile-menu-drawer';
        drawer.innerHTML = `
            <div class="mobile-drawer-header">
                <a href="index.php" class="logo" style="text-decoration:none; display:flex; align-items:center; gap:0.5rem; color:var(--text-main); font-size:1.2rem;">
                    <img src="logo.png" alt="Digi Pro X 24" style="height:28px; border-radius: 6px;">
                    Digi <span>Pro X 24</span>
                </a>
                <button class="mobile-drawer-close" onclick="toggleMobileMenu()">✕</button>
            </div>
            <div class="mobile-drawer-content">
                <ul class="mobile-drawer-links">
                    ${document.querySelector('ul.nav-links') ? document.querySelector('ul.nav-links').innerHTML : `
                        <li><a href="index.php" class="${isHome ? 'active' : ''}">Home</a></li>
                        <li><a href="categories.php" class="${isCat ? 'active' : ''}">Categories</a></li>
                        <li><a href="products.php" class="${isProd ? 'active' : ''}">Products</a></li>
                        <li><a href="about.php" class="${isAbout ? 'active' : ''}">About</a></li>
                        <li><a href="contact.php" class="${isContact ? 'active' : ''}">Contact</a></li>
                    `}
                </ul>
                <div class="mobile-drawer-auth" id="mobileDrawerAuth"></div>
            </div>
        `;

        document.body.appendChild(overlay);
        document.body.appendChild(drawer);

        const headerActions = document.querySelector('.header-actions');
        if (headerActions && !document.getElementById('hamburgerBtn')) {
            const btn = document.createElement('button');
            btn.id = 'hamburgerBtn';
            btn.className = 'hamburger-btn';
            btn.onclick = toggleMobileMenu;
            btn.innerHTML = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            `;
            headerActions.appendChild(btn);
        }

        const authContainer = document.getElementById('mobileDrawerAuth');
        if (authContainer) {
            const headerBtns = document.querySelectorAll('.header-actions a, .header-actions button');
            headerBtns.forEach(btn => {
                if (btn.classList.contains('cart-btn') || btn.classList.contains('hamburger-btn') || btn.innerText.includes('🔍') || btn.id === 'currencyToggle') {
                    return;
                }
                const clone = btn.cloneNode(true);
                clone.style.display = 'block';
                clone.style.width = '100%';
                clone.style.textAlign = 'center';
                clone.style.marginRight = '0';
                authContainer.appendChild(clone);
            });
        }
    };

    const toggleMobileMenu = () => {
        const overlay = document.getElementById('mobileDrawerOverlay');
        const drawer = document.getElementById('mobileMenuDrawer');
        if (overlay && drawer) {
            const isActive = drawer.classList.contains('active');
            if (isActive) {
                overlay.classList.remove('active');
                drawer.classList.remove('active');
                document.body.style.overflow = 'auto';
            } else {
                overlay.classList.add('active');
                drawer.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
    };

    window.toggleMobileMenu = toggleMobileMenu;

    injectMobileNav();
    injectMobileDrawer();
    window.addEventListener('resize', () => {
        injectMobileNav();
        injectMobileDrawer();
    });
    
    // Periodically sync cart count badge
    setInterval(() => {
        const badge = document.querySelector('.mobile-cart-badge');
        const sourceBadge = document.querySelector('.header-actions .cart-count');
        if (badge && sourceBadge) {
            badge.innerText = sourceBadge.innerText;
        }
    }, 500);

    // FORCE WHITE TEXT ON ALL INPUT FIELDS (Fallback)
    // Fix existing inputs
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(function(input) {
        input.style.color = '#1a1a1a';
        input.style.backgroundColor = '#ffffff';
        
        // Fix on focus
        input.addEventListener('focus', function() {
            this.style.color = '#1a1a1a';
        });
        
        // Fix on blur
        input.addEventListener('blur', function() {
            this.style.color = '#1a1a1a';
        });
        
        // Fix on input
        input.addEventListener('input', function() {
            this.style.color = '#1a1a1a';
        });
    });
    
    // Fix dynamically created inputs
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) {
                    if (node.matches && node.matches('input, textarea, select')) {
                        node.style.color = '#1a1a1a';
                        node.style.backgroundColor = '#ffffff';
                    }
                    const childInputs = node.querySelectorAll ? node.querySelectorAll('input, textarea, select') : [];
                    childInputs.forEach(function(child) {
                        child.style.color = '#1a1a1a';
                        child.style.backgroundColor = '#ffffff';
                    });
                }
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    // --- Currency Converter System ---
    let currentCurrency = localStorage.getItem('site_currency') || 'LKR';
    const EXCHANGE_RATE = 320;

    const injectCurrencyToggle = () => {
        let btn = document.getElementById('currencyToggle');
        
        if (!btn) {
            btn = document.createElement('button');
            btn.id = 'currencyToggle';
            btn.style.cssText = 'background: rgba(255, 94, 0, 0.08); border: 1.5px solid var(--primary-glow); color: var(--primary-glow); padding: 0.45rem 1rem; border-radius: 8px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; margin-left: 0.8rem; box-shadow: 0 0 10px rgba(255, 94, 0, 0.1); height: 38px; white-space: nowrap;';
            
            const headerActions = document.querySelector('.header-actions');
            if (headerActions) {
                headerActions.style.display = 'flex';
                headerActions.style.alignItems = 'center';
                
                const hamburger = document.getElementById('hamburgerBtn') || headerActions.querySelector('.hamburger-btn');
                if (hamburger) {
                    headerActions.insertBefore(btn, hamburger);
                } else {
                    headerActions.appendChild(btn);
                }
            } else {
                // Fallback
                btn.style.position = 'fixed';
                btn.style.bottom = '80px';
                btn.style.right = '20px';
                btn.style.zIndex = '9999';
                document.body.appendChild(btn);
            }
        }
        
        btn.innerHTML = currentCurrency === 'LKR' ? '🇱🇰 LKR' : '🇺🇸 USD';
        
        // Premium hover effects
        btn.onmouseenter = () => {
            btn.style.background = 'var(--primary-glow)';
            btn.style.color = '#ffffff';
            btn.style.boxShadow = '0 0 15px rgba(255, 94, 0, 0.4)';
            btn.style.transform = 'translateY(-1px)';
        };
        btn.onmouseleave = () => {
            btn.style.background = 'rgba(255, 94, 0, 0.08)';
            btn.style.color = 'var(--primary-glow)';
            btn.style.boxShadow = '0 0 10px rgba(255, 94, 0, 0.1)';
            btn.style.transform = 'translateY(0)';
        };
        
        btn.onclick = () => {
            currentCurrency = currentCurrency === 'LKR' ? 'USD' : 'LKR';
            localStorage.setItem('site_currency', currentCurrency);
            btn.innerHTML = currentCurrency === 'LKR' ? '🇱🇰 LKR' : '🇺🇸 USD';
            applyCurrency();
        };
    };

    const wrapPrices = () => {
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
        const nodesToWrap = [];
        const skipTags = ['SCRIPT', 'STYLE', 'OPTION', 'TEXTAREA', 'TITLE'];
        
        while (walker.nextNode()) {
            const node = walker.currentNode;
            if (node.parentNode && skipTags.includes(node.parentNode.tagName)) continue;
            if (node.parentNode && node.parentNode.classList && node.parentNode.classList.contains('price-wrapped')) continue;

            const regexLKR = /Rs\.\s*([\d,]+(?:\.\d+)?)/g;
            if (regexLKR.test(node.nodeValue)) {
                nodesToWrap.push({ node, regex: /Rs\.\s*([\d,]+(?:\.\d+)?)/g });
            }
        }

        nodesToWrap.forEach(item => {
            const node = item.node;
            const regex = item.regex;
            if (!node.parentNode) return;

            const fragment = document.createDocumentFragment();
            const text = node.nodeValue;
            let lastIndex = 0;
            let match;
            
            while ((match = regex.exec(text)) !== null) {
                if (match.index > lastIndex) {
                    fragment.appendChild(document.createTextNode(text.substring(lastIndex, match.index)));
                }
                const span = document.createElement('span');
                span.className = 'price-wrapped';
                
                const val = parseFloat(match[1].replace(/,/g, ''));
                span.setAttribute('data-lkr', val);
                span.textContent = match[0];
                
                fragment.appendChild(span);
                lastIndex = regex.lastIndex;
            }
            
            if (lastIndex < text.length) {
                fragment.appendChild(document.createTextNode(text.substring(lastIndex)));
            }
            
            node.parentNode.replaceChild(fragment, node);
        });
    };

    const applyCurrency = () => {
        document.querySelectorAll('.price-wrapped').forEach(span => {
            const lkr = parseFloat(span.getAttribute('data-lkr'));
            if (isNaN(lkr)) return;
            
            if (currentCurrency === 'USD') {
                const usd = lkr / EXCHANGE_RATE;
                span.textContent = '$' + usd.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                span.textContent = 'Rs. ' + lkr.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        });
    };

    const priceObserver = new MutationObserver((mutations) => {
        let shouldUpdate = false;
        for (let i = 0; i < mutations.length; i++) {
            const m = mutations[i];
            if (m.target && m.target.classList && m.target.classList.contains('price-wrapped')) continue;
            if (m.target && m.target.parentNode && m.target.parentNode.classList && m.target.parentNode.classList.contains('price-wrapped')) continue;
            
            if (m.addedNodes.length > 0 || m.type === 'characterData') {
                shouldUpdate = true;
                break;
            }
        }
        
        if (shouldUpdate) {
            clearTimeout(window.priceDebounce);
            window.priceDebounce = setTimeout(() => {
                priceObserver.disconnect();
                wrapPrices();
                applyCurrency();
                priceObserver.observe(document.body, { childList: true, subtree: true, characterData: true });
            }, 150);
        }
    });

    // Initialize currency
    setTimeout(() => {
        injectCurrencyToggle();
        wrapPrices();
        applyCurrency();
        priceObserver.observe(document.body, { childList: true, subtree: true, characterData: true });
    }, 100);

    // ── Non-Logged-In Guest User 10-Second Login Prompt & Redirect ──
    const initGuestLoginPrompt = () => {
        const currentPath = window.location.pathname.toLowerCase();
        // Ignore auth pages & admin panel
        if (currentPath.includes('login.php') || 
            currentPath.includes('forgot_password.php') || 
            currentPath.includes('change_password.php') || 
            currentPath.includes('/admin/')) {
            return;
        }

        // Check if user is logged in (logout link present in DOM)
        const isLoggedIn = Boolean(
            document.querySelector('a[href*="logout.php"]') || 
            document.querySelector('a[href*="logout"]') ||
            window.isUserLoggedIn === true
        );

        if (isLoggedIn) return;

        // 10 Seconds countdown before triggering popup & redirect
        setTimeout(() => {
            if (document.getElementById('guestLoginModal')) return;

            const pathName = window.location.pathname;
            const pageName = pathName.substring(pathName.lastIndexOf('/') + 1) || 'index.php';
            const redirectUrl = `login.php?redirect=${encodeURIComponent(pageName)}`;

            const modal = document.createElement('div');
            modal.id = 'guestLoginModal';
            modal.style.cssText = `
                position: fixed;
                inset: 0;
                background: rgba(5, 6, 8, 0.88);
                backdrop-filter: blur(14px);
                -webkit-backdrop-filter: blur(14px);
                z-index: 9999999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
                animation: guestModalFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            `;

            modal.innerHTML = `
                <div class="guest-modal-card">
                    <div class="guest-modal-icon">🔒</div>
                    <h2 class="guest-modal-title">Login Required to Continue</h2>
                    <p class="guest-modal-desc">
                        Please sign in or create an account to continue browsing products, using services, and shopping on <strong style="color: #ffffff;">Digi Pro X 24</strong>.
                    </p>
                    <div class="guest-modal-badge">
                        <span>⏳</span>
                        <span>Redirecting to Login in <span id="guestRedirectCount">30</span>s...</span>
                    </div>
                    <a href="${redirectUrl}" class="guest-modal-btn">Sign In / Register Now ➔</a>
                </div>
            `;

            const style = document.createElement('style');
            style.textContent = `
                @keyframes guestModalFadeIn {
                    from { opacity: 0; transform: scale(0.92); }
                    to { opacity: 1; transform: scale(1); }
                }
                .guest-modal-card {
                    background: linear-gradient(145deg, rgba(20, 24, 33, 0.96), rgba(12, 15, 22, 0.98));
                    border: 1px solid rgba(255, 94, 0, 0.3);
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.75), 0 0 30px rgba(255, 94, 0, 0.15);
                    border-radius: 20px;
                    padding: 2.2rem 1.75rem;
                    max-width: 440px;
                    width: 100%;
                    text-align: center;
                    position: relative;
                    color: #ffffff;
                    font-family: 'Outfit', sans-serif;
                    box-sizing: border-box;
                }
                .guest-modal-icon {
                    width: 64px;
                    height: 64px;
                    margin: 0 auto 1.2rem;
                    background: rgba(255, 94, 0, 0.1);
                    border: 2px solid var(--primary-glow, #ff5e00);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.8rem;
                    box-shadow: 0 0 20px rgba(255, 94, 0, 0.3);
                }
                .guest-modal-title {
                    font-size: 1.45rem;
                    font-weight: 800;
                    margin: 0 0 0.6rem 0;
                    color: #ffffff;
                    line-height: 1.3;
                }
                .guest-modal-desc {
                    font-size: 0.92rem;
                    color: #94a3b8;
                    line-height: 1.55;
                    margin: 0 0 1.25rem 0;
                }
                .guest-modal-badge {
                    background: rgba(255, 94, 0, 0.06);
                    border: 1px dashed rgba(255, 94, 0, 0.25);
                    border-radius: 12px;
                    padding: 0.65rem 0.85rem;
                    font-size: 0.84rem;
                    color: var(--primary-glow, #ff5e00);
                    font-weight: 700;
                    margin-bottom: 1.5rem;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.4rem;
                    max-width: 100%;
                    box-sizing: border-box;
                }
                .guest-modal-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    width: 100%;
                    padding: 0.85rem 1.2rem;
                    background: linear-gradient(135deg, #ff5e00, #ff8700);
                    color: #ffffff !important;
                    font-weight: 800;
                    font-size: 0.95rem;
                    border-radius: 50px;
                    text-decoration: none !important;
                    box-shadow: 0 4px 20px rgba(255, 94, 0, 0.4);
                    transition: all 0.25s ease;
                    letter-spacing: 0.5px;
                    text-transform: uppercase;
                    box-sizing: border-box;
                }
                .guest-modal-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 25px rgba(255, 94, 0, 0.55);
                }
                @media (max-width: 480px) {
                    .guest-modal-card {
                        padding: 1.6rem 1.1rem;
                        border-radius: 18px;
                        max-width: 92vw;
                    }
                    .guest-modal-icon {
                        width: 52px;
                        height: 52px;
                        font-size: 1.45rem;
                        margin-bottom: 0.9rem;
                    }
                    .guest-modal-title {
                        font-size: 1.25rem;
                        margin-bottom: 0.5rem;
                    }
                    .guest-modal-desc {
                        font-size: 0.85rem;
                        line-height: 1.45;
                        margin-bottom: 1rem;
                    }
                    .guest-modal-badge {
                        font-size: 0.8rem;
                        padding: 0.55rem 0.7rem;
                        margin-bottom: 1.2rem;
                    }
                    .guest-modal-btn {
                        padding: 0.8rem 1rem;
                        font-size: 0.88rem;
                    }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(modal);

            // 30-second countdown on popup before navigating to login screen if user doesn't interact
            let countdown = 30;
            const countEl = document.getElementById('guestRedirectCount');
            const timer = setInterval(() => {
                countdown--;
                if (countEl) countEl.textContent = countdown;
                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.href = redirectUrl;
                }
            }, 1000);
        }, 10000); // 10 seconds timer using system
    };

    initGuestLoginPrompt();
});
