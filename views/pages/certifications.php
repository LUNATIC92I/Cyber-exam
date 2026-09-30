<?php use App\Core\View; ?>
<section class="page-head">
    <div class="container">
        <div class="eyebrow fade-up">Catalogue</div>
        <h1 class="fade-up" data-delay="60">Certifications</h1>
        <p class="fade-up" data-delay="120">Every exam: 100 scenario-based questions, a server-controlled timer, automated scoring and a verifiable certificate when you score 70% or more.</p>
    </div>
</section>
<section class="container">
    <div class="cert-grid">
        <?php foreach ($certifications as $i => $c): ?>
            <?= View::partial('cert-card', ['c' => $c, 'access' => $user ? ($access[$c['id']] ?? ['state' => 'pay']) : null, 'delay' => ($i % 3) * 90]) ?>
        <?php endforeach; ?>
    </div>
</section>
