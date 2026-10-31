<?php if (!empty($authPage) || !auth_user()): ?>
</main>
<?php else: ?>
    </div><!-- /content-area -->
</div><!-- /main-content -->
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/script.js')) ?>"></script>
</body>
</html>
