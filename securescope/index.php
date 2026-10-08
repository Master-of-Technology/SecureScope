<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    $user = current_user();
    redirect(role_home_path($user['role_name']));
}

render_public_header('الرئيسية');
?>
<section class="hero-section hero-section-image">
    <div class="hero-content hero-content-image">
        <div class="hero-copy">
            <p class="eyebrow">SecureScope للأمن السيبراني</p>
            <h1>نساعدك على فهم المخاطر الرقمية ومعالجتها بثقة.</h1>
            <p class="hero-text">تقدم SecureScope تقييمات أمنية منظمة تساعد الشركات على اكتشاف الثغرات، تحديد أولوياتها، ومتابعة معالجتها ضمن مسار واضح.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(url('services.php')) ?>">استكشف خدماتنا</a>
                <a class="button button-secondary" href="<?= e(url('login.php')) ?>">الدخول إلى البوابة</a>
            </div>
            <ul class="hero-pills" aria-label="مزايا النظام">
                <li>إدارة المشاريع</li>
                <li>توثيق النتائج</li>
                <li>متابعة المعالجات</li>
            </ul>
        </div>
    </div>
</section>

<section class="public-section" aria-labelledby="services-summary-heading">
    <div class="section-heading">
        <div>
            <p class="eyebrow">خدماتنا</p>
            <h2 id="services-summary-heading">تقييمات عملية تناسب احتياج عملك.</h2>
        </div>
        <a href="<?= e(url('services.php')) ?>">عرض كل الخدمات ←</a>
    </div>
    <div class="service-grid">
        <article class="service-card"><span class="service-icon">⌘</span><h3>تقييم تطبيقات الويب</h3><p>فحص الوظائف المكشوفة وحماية بيانات المستخدمين.</p></article>
        <article class="service-card"><span class="service-icon">◉</span><h3>تقييم أمن الشبكات</h3><p>مراجعة الأنظمة والخدمات المتاحة على الشبكة.</p></article>
        <article class="service-card"><span class="service-icon">✓</span><h3>التدقيق الأمني</h3><p>قياس فاعلية الضوابط والإجراءات الأمنية.</p></article>
    </div>
</section>

<section class="public-section workflow-section" aria-labelledby="workflow-heading">
    <div class="section-heading section-heading-centered">
        <div>
            <p class="eyebrow">مسار واضح</p>
            <h2 id="workflow-heading">من الملاحظة إلى المعالجة.</h2>
        </div>
    </div>
    <div class="workflow-grid">
        <article><strong>01</strong><h3>نحدّد النطاق</h3><p>نربط التقييم بأصول الشركة والمجال المطلوب.</p></article>
        <article><strong>02</strong><h3>نوثّق النتيجة</h3><p>نسجل المخاطر والأدلة والتوصيات بصورة منظمة.</p></article>
        <article><strong>03</strong><h3>نتابع التحسين</h3><p>يتابع العميل المعالجة ويجري الفريق التحقق.</p></article>
    </div>
</section>

<section class="public-cta">
    <div>
        <p class="eyebrow">بوابة العملاء والفريق</p>
        <h2>هل لديك حساب في SecureScope؟</h2>
        <p>سجّل دخولك للوصول إلى مشاريعك أو تقييماتك المسندة.</p>
    </div>
    <a class="button button-light" href="<?= e(url('login.php')) ?>">تسجيل الدخول</a>
</section>
<?php render_footer(); ?>
