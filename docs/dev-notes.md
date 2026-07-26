# ملاحظات تطوير — Zarzour Sport

## أسماء صلاحيات Shield

Filament Shield يولّد أسماء الصلاحيات بصيغة `Action:Model` (PascalCase + `:`)
حسب `config/filament-shield.php` (`permissions.case = pascal`, `permissions.separator = ':'`).

مثال: صلاحية عرض قائمة الطلبات هي **`ViewAny:Order`**، وليست `view_Order` أو
`viewAny_order` أو أي صيغة snake_case. نفس النمط لكل Resource: `View:Order`,
`Create:Order`, `Update:Order`, `Delete:Order`, إلخ.

عند كتابة أي كود يتحقق من صلاحية يدوياً (مثل `$user->can(...)` أو
`User::query()->permission(...)` كما في `App\Observers\OrderObserver`)، استخدم
الصيغة الصحيحة `ViewAny:Order` — استخدام صيغة خاطئة لا يسبب خطأ فوري، بل يفشل
بصمت (الصلاحية غير موجودة فتُرجع false دائماً)، وهذا بالضبط سبب فجوة
`BannerResource` السابقة.

## تسمية `total` بـ PriceCalculationService مقابل `orders.total`

- `PriceCalculationService::calculate()` يُرجع `total_before_shipping`: المجموع
  بعد كل الخصومات (مباشر + كوبون + عرض) لكن **قبل** إضافة رسوم الشحن.
- نفس الدالة تُرجع أيضاً `grand_total`: `total_before_shipping + shipping_fee`،
  وهو الرقم النهائي شامل الشحن.
- عمود `orders.total` بقاعدة البيانات (ويُعاد بنفس الاسم `total` بردود
  `POST /api/v1/orders` و `OrderResource` الخاص بواجهة API) يخزّن **`grand_total`**
  دائماً، وليس `total_before_shipping`.

قبل التغيير (توحيد المهمة 3 من برومت التنظيف الشامل)، كان المفتاح المُرجع من
`PriceCalculationService` باسم `total` فقط — بمعنى مختلف عن `orders.total` بنفس
الاسم بالضبط، ما كان يسبب لبساً حقيقياً بين استجابة `/api/v1/cart/calculate`
(حيث `total` كانت تعني "قبل الشحن") واستجابة `/api/v1/orders` (حيث `total`
تعني "شامل الشحن"). تم فحص كامل المستودع ولا يوجد أي Postman collection أو
عميل خارجي موثّق يعتمد على الاسم القديم، لذا أُعيدت التسمية إلى
`total_before_shipping` بدل توثيق اللبس فقط. **إذا كان هناك تطبيق جوال أو
واجهة أمامية خارج هذا المستودع تستهلك `/api/v1/cart/calculate`، يجب التحقق من
توافقها مع الاسم الجديد قبل النشر.**
