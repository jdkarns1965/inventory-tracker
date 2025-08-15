    </main>

    <!-- Bottom Navigation for Mobile -->
    <nav class="navbar navbar-light bg-light border-top fixed-bottom d-lg-none">
        <div class="container-fluid">
            <div class="row w-100 text-center">
                <?php 
                $mobileNav = array_slice($navItems, 0, 4); // Show only first 4 items
                foreach ($mobileNav as $item): 
                ?>
                    <div class="col">
                        <a href="<?= htmlspecialchars($item['url']) ?>" 
                           class="nav-link py-2 <?= $currentPage === $item['url'] ? 'text-primary' : 'text-muted' ?>">
                            <i data-feather="<?= htmlspecialchars($item['icon']) ?>" class="mb-1"></i>
                            <small class="d-block"><?= htmlspecialchars($item['title']) ?></small>
                        </a>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($navItems) > 4): ?>
                    <div class="col">
                        <a href="#" class="nav-link py-2 text-muted" data-bs-toggle="modal" data-bs-target="#moreMenuModal">
                            <i data-feather="more-horizontal" class="mb-1"></i>
                            <small class="d-block">More</small>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- More Menu Modal for Mobile -->
    <?php if (count($navItems) > 4): ?>
    <div class="modal fade" id="moreMenuModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group list-group-flush">
                        <?php 
                        $remainingNav = array_slice($navItems, 4);
                        foreach ($remainingNav as $item): 
                        ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>" class="list-group-item list-group-item-action">
                                <i data-feather="<?= htmlspecialchars($item['icon']) ?>" class="me-2"></i>
                                <?= htmlspecialchars($item['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Floating Action Button -->
    <?php if (hasPermission('update_inventory') && !in_array($currentPage, ['inventory.php'])): ?>
    <div class="fab-container">
        <a href="inventory.php" class="fab" title="Quick Inventory Update">
            <i data-feather="edit"></i>
        </a>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script src="js/app.js"></script>
    <script>
        // Initialize Feather icons
        feather.replace();
        
        // Add some padding to body for mobile bottom nav
        if (window.innerWidth < 992) {
            document.body.style.paddingBottom = '80px';
        }
    </script>
</body>
</html>