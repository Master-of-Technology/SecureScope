<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('Security Manager');

$metrics = [
    'العملاء النشطون' => (int) db()->query(
        "SELECT COUNT(*)
         FROM clients
         WHERE status = 'active'"
    )->fetchColumn(),

    'المشاريع النشطة' => (int) db()->query(
        "SELECT COUNT(*)
         FROM projects
         WHERE status NOT IN ('closed', 'cancelled')"
    )->fetchColumn(),

    'التقييمات المفتوحة' => (int) db()->query(
        "SELECT COUNT(*)
         FROM assessments
         WHERE status IN ('pending', 'in_progress', 'under_review')"
    )->fetchColumn(),

    'الثغرات بانتظار المراجعة' => (int) db()->query(
        "SELECT COUNT(*)
         FROM findings
         WHERE status = 'under_review'"
    )->fetchColumn(),

    'المعالجات بانتظار التحقق' => (int) db()->query(
        "SELECT COUNT(*)
         FROM remediations
         WHERE status IN ('submitted', 'resolved')"
    )->fetchColumn(),

    'طلبات التسجيل بانتظار المراجعة' => (int) db()->query(
        "SELECT COUNT(*)
         FROM registration_requests
         WHERE status = 'pending'"
    )->fetchColumn(),

    'طلبات المحللين بانتظار المراجعة' => (int) db()->query(
        "SELECT COUNT(*)
         FROM analyst_applications
         WHERE status = 'pending'"
    )->fetchColumn(),
];

render_header('لوحة تحكم المدير');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">مدير الأمن</p>
        <h1>
            لوحة تحكم المدير
        </h1>
        <p class="muted">
            تابع مشاريع العملاء والتقييمات الأمنية وراجع الأعمال
            والطلبات التي تحتاج إلى قرار.
        </p>
    </div>
</section>

<section class="metric-grid" aria-label="ملخص المدير">
    <?php foreach ($metrics as $label => $value): ?>
        <article class="metric-card">
            <span>
                <?= e($label) ?>
            </span>
            <strong>
                <?= e((string) $value) ?>
            </strong>
        </article>
    <?php endforeach; ?>
</section>

<section class="details-card quick-actions-card">
    <div class="section-heading compact">
        <div>
            <h2>
                إدارة النظام
            </h2>
            <p class="muted">
                الوصول السريع إلى أهم وحدات إدارة SecureScope.
            </p>
        </div>
    </div>

    <div class="quick-actions">
        <a class="quick-action" href="<?= e(
            url('manager/clients/index.php')
        ) ?>">
            <strong>
                العملاء
            </strong>
            <span>
                مراجعة الشركات والعملاء المعتمدين.
            </span>
        </a>

        <a class="quick-action" href="<?= e(
            url('manager/users/index.php')
        ) ?>">
            <strong>
                المستخدمون
            </strong>
            <span>
                إدارة الحسابات والأدوار وحالات المستخدمين.
            </span>
        </a>

        <a class="quick-action" href="<?= e(
            url(
                'manager/analyst-applications/index.php'
            )
        ) ?>">
            <strong>
                طلبات المحللين الأمنيين
            </strong>
            <span>
                مراجعة طلبات الانضمام ومستندات المتقدمين.
            </span>
        </a>

        <a class="quick-action" href="<?= e(
            url('manager/projects/index.php')
        ) ?>">
            <strong>
                المشاريع
            </strong>
            <span>
                إنشاء ومتابعة مشاريع التقييم الأمني.
            </span>
        </a>
        <a class="quick-action" href="<?= e(url('manager/project-requests/index.php')) ?>">
            <strong>طلبات المشاريع</strong>
            <span>مراجعة طلبات العملاء والموافقة عليها أو رفضها.</span>
        </a>
        <a class="quick-action" href="<?= e(
    url('manager/assets/index.php')) ?>">
    <strong>
        الأصول
    </strong>
    <span>
        إدارة الأنظمة والخدمات والأصول التابعة للعملاء.
    </span>
    </a>
        <a class="quick-action" href="<?= e(
            url('manager/assessments/index.php')
        ) ?>">
            <strong>
                التقييمات الأمنية
            </strong>
            <span>
                إدارة التقييمات وإسنادها إلى المحللين الأمنيين.
            </span>
        </a>

        <a class="quick-action" href="<?= e(
            url('manager/findings/index.php')
        ) ?>">
            <strong>
                الثغرات الأمنية
            </strong>
            <span>
                مراجعة النتائج والثغرات المكتشفة أثناء التقييمات.
            </span>
        </a>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>
                سير العمل
            </h2>
            <p class="muted">
                التسلسل الأساسي لإدارة عمليات SecureScope.
            </p>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">
                1. العميل
            </span>
            <strong>
                تسجيل وإدارة العملاء.
            </strong>
        </div>
        <div>
            <span class="detail-label">
                2. المشروع
            </span>
            <strong>
                إنشاء مشروع التقييم الأمني.
            </strong>
        </div>
        <div>
            <span class="detail-label">
                3. الأصول
            </span>
            <strong>
                تحديد الأنظمة والخدمات التي سيتم فحصها.
            </strong>
        </div>
        <div>
            <span class="detail-label">
                4. الإسناد والتقييم
            </span>
            <strong>
                إسناد المشروع والتقييم إلى المحلل الأمني.
            </strong>
        </div>
        <div>
            <span class="detail-label">
                5. Findings
            </span>
            <strong>
                تسجيل الثغرات والنتائج المكتشفة.
            </strong>
        </div>
        <div>
            <span class="detail-label">
                6. التقرير
            </span>
            <strong>
                إعداد التقرير ورفعه للجهات المصرح لها.
            </strong>
        </div>
        
    </div>
</section>  

<?php render_footer(); ?>