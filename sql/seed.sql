-- Seed data for lp_tifaw
-- Default admin: username=admin password=admin123  (change after first login)
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$10$31tRbVMWmuJYim/yEFQ9MeHVuLXPCIbTbqhv5t0TG0tkHMtTVg6Mi');

INSERT INTO settings (k, v) VALUES
('store_name','Pant Classe'),
('store_logo','https://lucci-moriny.sirv.com/Images/cl-01.jpg'),
('whatsapp','+212600000000'),
('support_phone','+212600000000'),
('fb_pixel_id','640658465078889'),
('tiktok_pixel_id','CM08HVJC77U7MRPGKD5G'),
('gtm_id','GTM-NPZSKMPJ'),
('ga_id',''),
('sheetdb_enabled','1'),
('sheetdb_url','https://sheetdb.io/api/v1/0tjsq029vh1s9'),
('sheetdb_token',''),
('accent_color','#a07a3c'),
('cta_color','#22c55e'),
('countdown_hours','25'),
('header_banner','التوصيل مجاني لجميع أنحاء المغرب'),
('policy_privacy','<h2>سياسة الخصوصية</h2><p>نحن نحترم خصوصيتك ولا نشارك بياناتك مع أي طرف ثالث.</p>'),
('policy_terms','<h2>شروط الاستخدام</h2><p>باستخدامك للموقع فأنت توافق على شروطنا.</p>'),
('policy_refund','<h2>سياسة الإرجاع</h2><p>يمكنك إرجاع المنتج خلال 10 أيام في حال وجود عيب أو عدم رضا.</p>');

INSERT INTO categories (name, slug, position) VALUES
('ملابس', 'apparel', 1);

-- ===== Product: Casual Pant Classe =====
INSERT INTO products
(category_id, title, slug, short_desc, full_desc, cover_image, base_price, compare_price, badges, status,
 seo_title, seo_description, og_image, sections_json)
VALUES
(1, 'سروال كاجوال كلاس', 'casual-pants',
 'سروال أنيق مريح للاستخدام اليومي - كيجي سواء مع الكلاس او سبور',
 'سروال مصنوع من قماش عالي الجودة، مريح بزاف في الاستعمال اليومي، يجمع بين الإطلالة الكلاسيكية والراحة العصرية. متوفر بثلاثة ألوان وخمسة مقاسات.',
 'https://lucci-moriny.sirv.com/Images/cl-01.jpg',
 249.00, 499.00, 'الأكثر مبيعا,شحن مجاني,الدفع عند الاستلام', 1,
 'سروال كاجوال كلاس - الدفع عند الاستلام في المغرب',
 'اطلب سروالك الكلاسيكي بأفضل سعر مع الدفع عند الاستلام في كل المغرب. خصم 50% + شحن مجاني.',
 'https://lucci-moriny.sirv.com/Images/cl-01.jpg',
 '{"hero":{"headline":"سروال كاجوال كلاس","subheadline":"إطلالة راقية وراحة طوال اليوم","badges":["مريح بزاف","كيجي سواء مع الكلاس او سبور","جودة عالية","توصيل سريع","الدفع عند الاستلام"],"cta":"اطلب الآن"},"features":[{"icon":"🚚","title":"الشحن مجاني","text":"توصيل سريع لجميع المدن المغربية"},{"icon":"💵","title":"الدفع عند الاستلام","text":"تدفع فقط عند استلام طلبك"},{"icon":"🛡️","title":"ضمان المنتج","text":"إرجاع مجاني خلال 10 أيام"},{"icon":"🎧","title":"خدمة ما بعد البيع","text":"فريقنا متوفر للإجابة على استفساراتك"}],"testimonials":[{"name":"مريم","text":"طلبت واحد لراجلي وصلو اليوم زوين بزاف و مريح"},{"name":"كريم","text":"سروال رائع، كيجي سواء مع الكلاس او سبور، كنمشي بيه الخدمة حيت مريح بزاف"},{"name":"أحمد","text":"تبارك الله عليكم منتوج في المستوى، الجودة عالية، التوصيل سريع. شكرا لكم"}],"faqs":[{"q":"هل تقبلون الدفع عند الإستلام؟","a":"نعم، نحن نقبل الدفع عند الإستلام في جميع المدن المغربية."},{"q":"كم فترة التوصيل؟","a":"فترة التوصيل تعتمد على المدينة، و عادة ما تكون بين 1 و 3 أيام عمل."},{"q":"هل يمكن إرجاع المنتج إذا لم يعجبني؟","a":"نعم، يمكنك إرجاع المنتج في حالة عدم الرضا عنه في غضون 10 أيام من تاريخ الشراء وبشرط أن يكون في حالته الأصلية."},{"q":"هل يوجد ضمان على المنتج؟","a":"نعم، ضمان المنتج يشمل عيوب التصنيع وتختلف مدة الضمان حسب المنتج."}],"countdown_title":"تخفيض 50% و الشحن السريع بالمجان","cta_text":"اطلب الآن واستفد من العرض"}'
);
SET @p1 := LAST_INSERT_ID();

INSERT INTO product_media (product_id, url, kind, position) VALUES
(@p1,'https://lucci-moriny.sirv.com/Images/cl-02.jpg','slider',1),
(@p1,'https://lucci-moriny.sirv.com/Images/cl-03.jpg','slider',2),
(@p1,'https://lucci-moriny.sirv.com/Images/cl-01.jpg','slider',3),
(@p1,'https://lucci-moriny.sirv.com/Images/pack-colors.jpg','gallery',1),
(@p1,'https://lucci-moriny.sirv.com/Images/body01.jpg','gallery',2),
(@p1,'https://lucci-moriny.sirv.com/Images/body02.jpg','gallery',3),
(@p1,'https://lucci-moriny.sirv.com/Images/body03.jpg','gallery',4),
(@p1,'https://lucci-moriny.sirv.com/Images/cl-05.jpg','gallery',5),
(@p1,'https://lucci-moriny.sirv.com/Images/cl-06.jpg','gallery',6),
(@p1,'https://lucci-moriny.sirv.com/Images/cl-07.jpg','gallery',7);

INSERT INTO product_option_groups (product_id, name, label, type, position, is_required) VALUES
(@p1, 'color', 'اللون',  'swatch', 1, 1),
(@p1, 'size',  'المقاس', 'select', 2, 1);
SET @g1 := (SELECT id FROM product_option_groups WHERE product_id=@p1 AND name='color');
SET @g2 := (SELECT id FROM product_option_groups WHERE product_id=@p1 AND name='size');

INSERT INTO product_option_values (group_id, value, swatch, position) VALUES
(@g1,'أسود',      '#111111', 1),
(@g1,'أزرق داكن', '#1f2a44', 2),
(@g1,'بيج',       '#c9b48a', 3);

INSERT INTO product_option_values (group_id, value, position) VALUES
(@g2,'S (38-40)', 1),
(@g2,'M (42)',    2),
(@g2,'L (44)',    3),
(@g2,'XL (46)',   4),
(@g2,'XXL (48)',  5);

INSERT INTO product_offers
(product_id, label, quantity, total_price, compare_price, is_recommended, free_shipping, is_default, requires_options, position) VALUES
(@p1,'واحد ب 249 درهم',                1, 249.00, 499.00, 0, 0, 1, 1, 1),
(@p1,'إثنان ب 459 فقط',                2, 459.00, 998.00, 1, 1, 0, 1, 2),
(@p1,'ثلاثة سراويل ب 629 درهم فقط',     3, 629.00,1497.00, 0, 1, 0, 1, 3);
