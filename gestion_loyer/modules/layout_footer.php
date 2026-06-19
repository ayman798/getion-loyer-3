  </main><!-- /.page-content -->
</div><!-- /.main-wrapper -->

<script src="<?= $baseUrl ?? '' ?>assets/js/main.js"></script>
<?php if (!empty($extraScripts)): ?>
  <?php foreach ($extraScripts as $src): ?>
    <script src="<?= esc($src) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
