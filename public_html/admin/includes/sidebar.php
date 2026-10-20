<?php $adminPage = basename($_SERVER['SCRIPT_NAME'], '.php'); ?>
<aside class="admin-sidebar">
    <div class="admin-sidebar__brand">RK Admin</div>
    <nav class="admin-sidebar__nav">
        <a href="dashboard.php" class="admin-sidebar__link <?= $adminPage === 'dashboard' ? 'admin-sidebar__link--active' : '' ?>">📊 Dashboard</a>
        <a href="edit-home.php" class="admin-sidebar__link <?= $adminPage === 'edit-home' ? 'admin-sidebar__link--active' : '' ?>">🏠 Home / About</a>
        <a href="edit-research.php" class="admin-sidebar__link <?= $adminPage === 'edit-research' ? 'admin-sidebar__link--active' : '' ?>">🔬 Research</a>
        <a href="edit-teachings.php" class="admin-sidebar__link <?= $adminPage === 'edit-teachings' ? 'admin-sidebar__link--active' : '' ?>">📚 Teaching</a>
        <a href="edit-publications.php" class="admin-sidebar__link <?= $adminPage === 'edit-publications' ? 'admin-sidebar__link--active' : '' ?>">📝 Publications</a>
        <a href="edit-presentations.php" class="admin-sidebar__link <?= $adminPage === 'edit-presentations' ? 'admin-sidebar__link--active' : '' ?>">🎤 Presentations</a>
        <a href="edit-media-appearances.php" class="admin-sidebar__link <?= $adminPage === 'edit-media-appearances' ? 'admin-sidebar__link--active' : '' ?>">📰 Media</a>
        
        <div style="padding: 0.5rem 1.5rem; font-size: 0.75rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.5rem;">Services Pages</div>
        <a href="edit-migration.php" class="admin-sidebar__link <?= $adminPage === 'edit-migration' ? 'admin-sidebar__link--active' : '' ?>">🌍 Migration Studies</a>
        <a href="edit-professional-service.php" class="admin-sidebar__link <?= $adminPage === 'edit-professional-service' ? 'admin-sidebar__link--active' : '' ?>">⚖️ Prof. Service</a>
        <a href="edit-community-engagement.php" class="admin-sidebar__link <?= $adminPage === 'edit-community-engagement' ? 'admin-sidebar__link--active' : '' ?>">🤝 Community</a>

        <a href="analytics.php" class="admin-sidebar__link <?= $adminPage === 'analytics' ? 'admin-sidebar__link--active' : '' ?>" style="margin-top: 0.5rem;">&#128200; Analytics</a>
        <a href="media-manager.php" class="admin-sidebar__link <?= $adminPage === 'media-manager' ? 'admin-sidebar__link--active' : '' ?>" style="margin-top: 0.5rem;">🖼 Media Manager</a>
        <a href="edit-footer.php" class="admin-sidebar__link <?= $adminPage === 'edit-footer' ? 'admin-sidebar__link--active' : '' ?>">📋 Footer</a>
        <a href="edit-contact.php" class="admin-sidebar__link <?= $adminPage === 'edit-contact' ? 'admin-sidebar__link--active' : '' ?>">✉️ Contact Info</a>
        <a href="change-password.php" class="admin-sidebar__link <?= $adminPage === 'change-password' ? 'admin-sidebar__link--active' : '' ?>">🔐 Security</a>
    </nav>
    <div class="admin-sidebar__footer">
        <span style="font-size: 0.8125rem; opacity: 0.6;">Logged in as <?= e(getAdminUsername()) ?></span>
        <a href="<?= SITE_URL ?>/" target="_blank" class="admin-sidebar__view-site">🌐 View Site</a>
        <a href="logout.php" class="admin-sidebar__logout" style="margin-top: 0.5rem;">Logout</a>
    </div>
</aside>