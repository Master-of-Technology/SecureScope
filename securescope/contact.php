<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

render_public_header('تواصل معنا');
?>
<section class="public-page-hero">
    <p class="eyebrow">تواصل معنا</p>
    <h1>لنبدأ من نطاق العمل الذي تحتاج إليه.</h1>
    <p>إذا كنت مهتمًا بتقييم أمني أو تريد معرفة المزيد عن خدمات SecureScope، تواصل مع فريقنا لمناقشة احتياج شركتك.</p>
</section>

<section class="public-section contact-grid">
    <article class="details-card public-card">
        <p class="eyebrow">البريد الإلكتروني</p>
        <h2>فريق SecureScope</h2>
        <p><a href="mailto:contact@securescope.test">contact@securescope.test</a></p>
        <p class="muted">عنوان تجريبي للمشروع الأكاديمي؛ يمكن استبداله ببيانات الشركة الحقيقية عند النشر.</p>
    </article>
    <article class="details-card public-card">
        <p class="eyebrow">لديك حساب بالفعل؟</p>
        <h2>الدخول إلى البوابة</h2>
        <p>يستطيع مديرو الأمن والمحللون والعملاء المسجلون الدخول إلى النظام من هنا.</p>
        <a class="button button-primary" href="<?= e(url('login.php')) ?>">تسجيل الدخول</a>
    </article>
</section>
<?php render_footer(); ?>
