<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

render_public_header('خدماتنا');
?>
<section class="public-page-hero">
    <p class="eyebrow">خدمات SecureScope</p>
    <h1>خدمات تقييم تساعدك على معرفة أين تركز جهودك الأمنية.</h1>
    <p>نقدّم تقييمات منظمة، ثم نوثّق النتائج والتوصيات ونتابع مسار المعالجة مع فريق العميل.</p>
</section>

<section class="public-section service-grid service-grid-large">
    <article class="service-card"><span class="service-icon">⌘</span><h2>تقييم تطبيقات الويب</h2><p>مراجعة وظائف تطبيقات الويب المكشوفة، آليات المصادقة، وصلاحيات الوصول بهدف اكتشاف الثغرات التي قد تؤثر في التطبيق وبياناته.</p></article>
    <article class="service-card"><span class="service-icon">◉</span><h2>تقييم أمن الشبكات</h2><p>فحص البنية الشبكية والخدمات المكشوفة للمساعدة على تحديد نقاط الضعف والمخاطر المرتبطة بالوصول إلى الأنظمة.</p></article>
    <article class="service-card"><span class="service-icon">✓</span><h2>التدقيق الأمني</h2><p>مراجعة الضوابط والسياسات والممارسات التشغيلية، وتقديم ملاحظات عملية لرفع مستوى النضج الأمني.</p></article>
    <article class="service-card"><span class="service-icon">⚙</span><h2>مراجعة الإعدادات</h2><p>تقييم الإعدادات ذات الصلة بالأمن في الخوادم والأنظمة والخدمات لاكتشاف الإعدادات غير الآمنة أو غير الملائمة.</p></article>
</section>

<section class="public-cta">
    <div>
        <p class="eyebrow">لعملائنا</p>
        <h2>تابع التقييمات والمعالجات من بوابتك.</h2>
        <p>بوابة SecureScope توفر رؤية منظمة لحالة المشاريع والنتائج المعتمدة.</p>
    </div>
    <a class="button button-light" href="<?= e(url('login.php')) ?>">الدخول إلى البوابة</a>
</section>
<?php render_footer(); ?>
