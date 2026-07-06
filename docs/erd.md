# Zarzour Sport — ERD (Epic 1: Database & Schema)

هذا المستند يوثّق تصميم قاعدة البيانات لمشروع Zarzour Sport كما تم الاتفاق عليه مع Team Lead. أي قرار مذكور هنا هو **نهائي** ولا يجب تغييره دون موافقة صريحة.

## قرارات معمارية أساسية (مُلزمة)

1. **لا يوجد جدول admins منفصل.** الإدارة تتم عبر جدول `users` الافتراضي (المُنشأ في Epic 0) + أدوار Spatie Permission: `super-admin`, `manager`. لا تسجيل عملاء (customers) كمستخدمين مطلقاً — لا يوجد Auth للزبائن.
2. **جدول `customers` تجميعي وليس علائقي.** يُستخدم فقط لتجميع بيانات الزبائن التاريخية (اسم، هاتف، عنوان) لأغراض عرض/تقارير مستقبلية. **لا يوجد أي FK من جدول آخر يشير إليه.** الكوبونات المخصصة لمستخدم تُربط عبر `phone_number` (نص عادي) وليس `customer_id`.
3. **الطلب يمكن أن يجمع كوبون + عرض معاً.** `orders.coupon_id` و `orders.applied_offer_id` كلاهما nullable ومستقلان تماماً، لا قيد DB يمنع وجود الاثنين معاً.
4. **الهدية = `order_item` عادي بسعر 0.** لا يوجد حقل "gift" في جدول `orders`. حقلا `is_gift` و `offer_id` أُضيفا إلى `order_items`.
5. **نظام حجز مخزون (Stock Reservation)** — أهم جزء بالـ Epic، مفصّل بالأسفل.
6. **الوسائط (صور/فيديو) عبر Spatie MediaLibrary حصراً** — لا جداول `product_media` أو `banner_media` يدوية. جدول `media` العام (من Epic 0) يغطي `Product` و `Banner`.

---

## منطق حجز المخزون (Stock Reservation) — بالتفصيل

- `products.stock_quantity`: الكمية الفعلية الموجودة بالمستودع.
- `products.reserved_quantity`: الكمية المحجوزة حالياً لصالح طلبات `pending`.
- **الكمية المتاحة للبيع** = `stock_quantity - reserved_quantity`. هذه **accessor** محسوبة على الموديل (`available_quantity`)، وليست عموداً مخزَّناً.

### دورة حياة الحجز
| الحدث | stock_quantity | reserved_quantity | stock_movements |
|---|---|---|---|
| إنشاء طلب `pending` | لا تغيير | `+= quantity` لكل عنصر | `type=reservation` |
| تأكيد الطلب (`confirmed` أو أي حالة تالية نهائية) | `-= quantity` | `-= quantity` | `type=confirmed_deduction` |
| إلغاء الطلب (`cancelled`) | لا تغيير | `-= quantity` (تحرير فقط) | `type=release` |
| انتهاء مهلة الحجز (`expired`, عبر Scheduled Command) | لا تغيير | `-= quantity` | `type=expired_release` |
| تعديل يدوي من لوحة التحكم (مستقبلاً) | حسب الحالة | — | `type=manual_adjustment` |

- لو الكمية المتاحة (`stock - reserved`) أقل من الكمية المطلوبة أو تساوي صفر: **يُمنع إنشاء الطلب فوراً** (validation في `OrderService`)، ويُطلق `App\Events\OutOfStockAttempted`.
- **مهلة الحجز التلقائية**: تُقرأ من `settings` بالمفتاح `stock_reservation_timeout_hours` (افتراضي 24). تُحسب `orders.reserved_until` تلقائياً عند إنشاء الطلب عبر `OrderObserver::creating()` (وليس بمنطق Controller).
- **Scheduled Command**: `ReleaseExpiredStockReservations` يعمل كل ساعة (`->hourly()`)، يفحص كل طلب `pending` حيث `reserved_until < now()`، يحرر حجزه، يسجل حركة `expired_release`، ويغيّر حالته إلى `expired`.
- **`stock_movements`** هو مصدر الحقيقة (source of truth) لأي تدقيق مستقبلي على المخزون — كل عملية أعلاه تُسجَّل فيه.

---

## الجداول

### categories
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| name | string | |
| slug | string unique | |
| is_active | boolean default true | |
| sort_order | unsignedInteger default 0 | |
| timestamps | | |

### products
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| name | string | |
| slug | string unique | |
| description | text nullable | |
| price | decimal(10,2) | |
| category_id | FK → categories, **restrict** on delete | |
| is_active | boolean default true | |
| extra_info | json nullable | معلومات إضافية حرة (مقاسات، مواصفات...) |
| stock_quantity | unsignedInteger default 0 | |
| reserved_quantity | unsignedInteger default 0 | |
| timestamps | | |

وسائط المنتج (صور/فيديو) عبر `InteractsWithMedia` — collections: `images`, `video`. لا جدول يدوي.

### provinces
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| name | string | |
| shipping_fee | decimal(10,2) | **placeholder** بانتظار تأكيد العميل للأجور الفعلية |
| timestamps | | |

### discounts
خصم مرتبط مباشرة بمنتج (مستقل عن نظام offers).
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| product_id | FK → products, **restrict** | |
| type | enum(percentage,fixed) | |
| value | decimal(10,2) | |
| starts_at | timestamp nullable | |
| ends_at | timestamp nullable | |
| is_active | boolean default true | |
| timestamps | | |

### coupons
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| code | string unique | |
| type | enum(percentage,fixed) | |
| value | decimal(10,2) | |
| scope | enum(general,user) | `general` = لأي زبون، `user` = مرتبط برقم هاتف محدد |
| phone_number | string nullable | مستخدم فقط عند scope=user، **بدون FK** (لا يوجد جدول customers علائقي) |
| min_order_amount | decimal(10,2) nullable | |
| usage_limit | unsignedInteger nullable | |
| used_count | unsignedInteger default 0 | |
| expires_at | timestamp nullable | |
| is_active | boolean default true | |
| timestamps | | |

### offers
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| product_id | FK → products, **restrict** | |
| type | enum(discount_only,discount_with_gift) | |
| discount_type | enum(percentage,fixed) | |
| discount_value | decimal(10,2) | |
| is_active | boolean default true | |
| timestamps | | |

### offer_gifts
منتجات الهدية المرتبطة بعرض (علاقة many-to-many منطقية عبر جدول محوري).
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| offer_id | FK → offers, **cascade** (حذف العرض يحذف هداياه) | |
| gift_product_id | FK → products, **restrict** | |
| timestamps | | |

### banners
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| title | string | |
| link_url | string nullable | |
| sort_order | unsignedInteger default 0 | |
| starts_at | timestamp nullable | |
| ends_at | timestamp nullable | |
| is_active | boolean default true | |
| timestamps | | |

الصورة عبر Media Library (collection: `image`).

### orders
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| customer_name | string | |
| phone_number | string | |
| province_id | FK → provinces, **restrict** | |
| shipping_address | text | |
| extra_notes | text nullable | |
| subtotal | decimal(10,2) | |
| discount_amount | decimal(10,2) default 0 | |
| shipping_fee | decimal(10,2) | |
| total | decimal(10,2) | |
| coupon_id | FK → coupons nullable, **set null** | |
| applied_offer_id | FK → offers nullable, **set null** | |
| payment_method | enum(cod,sham_cash,visa_ui) | |
| status | enum(pending,confirmed,shipped,delivered,cancelled,expired) | |
| reserved_until | timestamp nullable | يُحسب تلقائياً عند الإنشاء بناءً على `settings.stock_reservation_timeout_hours` |
| timestamps | | |

> ملاحظة: `coupon_id`/`applied_offer_id` استُخدم `set null` (وليس `restrict`) لأن الطلب سجل تاريخي أهم من بقاء الكوبون/العرض، وحذف كوبون منتهي لا يجب أن يمنعه — القيمة الفعلية (discount_amount) محفوظة أصلاً بالطلب.

### order_items
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| order_id | FK → orders, **cascade** (حذف الطلب يحذف عناصره) | |
| product_id | FK → products, **restrict** (حفاظ على السجل التاريخي) | |
| product_name_snapshot | string | نسخة من اسم المنتج وقت الطلب |
| unit_price_snapshot | decimal(10,2) | نسخة من السعر وقت الطلب |
| quantity | unsignedInteger | |
| line_total | decimal(10,2) | |
| is_gift | boolean default false | الهدية = عنصر عادي بسعر 0 |
| offer_id | FK → offers nullable, **set null** | العرض الذي ولّد هذه الهدية |
| timestamps | | |

### stock_movements
سجل حركة كامل — **مصدر الحقيقة** لتدقيق المخزون.
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| product_id | FK → products, **restrict** | |
| type | enum(reservation,release,confirmed_deduction,manual_adjustment,expired_release) | |
| quantity | integer | قيمة موقّعة (قد تكون سالبة حسب نوع الحركة) |
| order_id | FK → orders nullable, **set null** | |
| notes | text nullable | |
| created_by | FK → users nullable, **set null** | يُملأ فقط عند تعديل يدوي |
| timestamps | | |

### customers
جدول تجميعي فقط — **بدون أي FK وارد إليه من جداول أخرى**.
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| name | string | |
| phone_number | string unique | |
| address | string nullable | |
| timestamps | | |

يُحدَّث (upsert) تلقائياً عبر `OrderObserver::created()` عند كل طلب جديد.

### settings
| عمود | نوع | ملاحظات |
|---|---|---|
| id | bigint PK | |
| key | string unique | |
| value | text | |
| type | enum(string,integer,boolean,json) | لتحديد كيفية cast القيمة عند القراءة |
| timestamps | | |

يحتوي إلزامياً `stock_reservation_timeout_hours` (قيمة 24).

---

## قرارات اجتهدت فيها (لم يحددها البرومت بدقة)

- **onDelete لـ `coupon_id`/`applied_offer_id` في orders**: استخدمت `set null` بدلاً من `restrict`، لأن الطلب سجل تاريخي مستقل (القيم الفعلية محفوظة كـ snapshot في `discount_amount`/`total`)، وحذف كوبون/عرض قديم لا يجب أن يمنعه أحد من الحذف. باقي القيود المذكورة صراحة بالبرومت (منتجات ↔ order_items/stock_movements) طبّقتها `restrict` كما طُلب.
- **`offer_gifts.offer_id`**: استخدمت `cascade` (حذف العرض يحذف سجلات هداياه) لأنها بيانات تابعة بالكامل للعرض ولا قيمة تاريخية مستقلة لها، بعكس `gift_product_id` الذي يبقى `restrict`.
- **`stock_movements.order_id` و `created_by`**: `set null` لأنها معلومات مرجعية إضافية (context) وليست جزءاً من سلامة السجل نفسه؛ حذف مستخدم أو طلب قديم لا يجب أن يمنع صيانة قاعدة البيانات.
- **`settings.type`**: أضفته لتحديد كيفية تحويل `value` (نص خام دائماً بقاعدة البيانات) عند القراءة في الكود، بما أنه غير مذكور صراحة لكنه ضروري عملياً لقراءة `stock_reservation_timeout_hours` كرقم صحيح.
- **`discounts` جدول مستقل عن `offers`**: أبقيت النظامين منفصلين تماماً كما وردا بالبرومت (discounts = خصم بسيط على منتج، offers = خصم + هدية اختيارية)، دون افتراض أي تكامل بينهما لأن البرومت لم يطلب ذلك.
