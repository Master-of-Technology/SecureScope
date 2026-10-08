<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

render_public_header('من نحن');
?>
<section class="public-page-hero">
    <p class="eyebrow">من نحن</p>
    <h1>رؤية أمنية واضحة للشركات التي تعتمد على التقنية.</h1>
    <p>SecureScope شركة متخصصة في إدارة تقييمات الأمن السيبراني. نساعد فرق العمل على تحويل نتائج التقييم المعقدة إلى أولويات واضحة وخطوات قابلة للتنفيذ.</p>
</section>

<section class="public-section two-column-section">
    <div class="details-card public-card">
        <h2>ما الذي يميز منهجنا؟</h2>
        <ul class="check-list">
            <li>نتائج مرتبطة بأصول الشركة الفعلية.</li>
            <li>تصنيف واضح للأولوية ومستوى الخطورة.</li>
            <li>أدلة وتوصيات قابلة للمراجعة.</li>
            <li>متابعة للمعالجات حتى التحقق منها.</li>
        </ul>
    </div>
     <div class="details-card public-card">
        <p class="eyebrow">رسالتنا</p>
        <h2>جعل إدارة المخاطر أكثر تنظيمًا وفهمًا.</h2>
        <p>الأمن ليس تقريرًا ينتهي عند تسليمه؛ بل دورة تبدأ بتحديد الأصول، ثم اكتشاف المخاطر، وتنتهي بالتحقق من المعالجة.</p>
    </div>
</section>

<section class="public-section values-section" aria-labelledby="values-heading">
    <div class="section-heading"><div><h1 class="eyebrow">قيمنا</h1><h2 id="values-heading">هي أن نعرف كيف نرضي عملائنا</h2></div></div>
    <div class="service-grid">
        <article class="service-card"><h3>الوضوح</h3><p>نشرح المخاطر بلغة منظمة تساعد على اتخاذ القرار.</p></article>
        <article class="service-card"><h3>المسؤولية</h3><p>نحافظ على توثيق كل خطوة مهمة ضمن مسار التقييم.</p></article>
        <article class="service-card"><h3>التحسين المستمر</h3><p>نركز على المعالجة والتحقق، وليس الاكتشاف فقط.</p></article>
    </div>
</section>
<?php render_footer(); ?>
