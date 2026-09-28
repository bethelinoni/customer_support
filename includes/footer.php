<?php
/**
 * BethelDesk - Shared Global Footer
 */
?>
        </main><!-- /.app-main-content -->
    </div><!-- /.app-layout -->

    <footer class="site-footer">
        <div class="site-footer-inner">
            <p>&copy; <?php echo date('Y'); ?> <strong><?php echo defined('SITE_NAME') ? SITE_NAME : 'BethelDesk'; ?></strong>. All rights reserved.</p>
            <div class="site-footer-meta">
                <span>Enterprise Customer Support</span>
                <span class="separator">&bull;</span>
                <span>v<?php echo defined('APP_VERSION') ? APP_VERSION : '2.4.0'; ?></span>
            </div>
        </div>
    </footer>

    <script>
        (function() {
            function initThemeToggle() {
                var buttons = document.querySelectorAll('.theme-toggle-btn, #themeToggleBtn, #mobileThemeToggleBtn');
                buttons.forEach(function(btn) {
                    if (btn.dataset.themeBound) return;
                    btn.dataset.themeBound = 'true';
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                        var next = current === 'dark' ? 'light' : 'dark';
                        document.documentElement.setAttribute('data-theme', next);
                        try {
                            localStorage.setItem('bethel_theme', next);
                        } catch(err) {}
                        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: next } }));
                    });
                });
            }

            function initMobileSidebar() {
                var toggleBtn = document.getElementById('mobileMenuToggleBtn');
                var backdrop = document.getElementById('appSidebarBackdrop');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        document.body.classList.toggle('sidebar-open');
                    });
                }
                if (backdrop) {
                    backdrop.addEventListener('click', function() {
                        document.body.classList.remove('sidebar-open');
                    });
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    initThemeToggle();
                    initMobileSidebar();
                });
            } else {
                initThemeToggle();
                initMobileSidebar();
            }
        })();
    </script>
</body>
</html>
