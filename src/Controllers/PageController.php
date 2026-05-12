<?php
class PageController {
    public function privacy(): void { $this->renderPolicy('سياسة الخصوصية', 'policy_privacy'); }
    public function terms(): void   { $this->renderPolicy('شروط الاستخدام',  'policy_terms'); }
    public function refund(): void  { $this->renderPolicy('سياسة الإرجاع',   'policy_refund'); }

    private function renderPolicy(string $title, string $key): void {
        $html = settings_get($key, '');
        render('policy', ['title' => $title, 'html' => $html]);
    }
}
