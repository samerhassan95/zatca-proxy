# ZATCA Proxy Service

خدمة وسيطة للتكامل مع هيئة الزكاة والضريبة والجمارك السعودية (ZATCA) - مصممة للعمل كجسر بين الخوادم المصرية وواجهة برمجة التطبيقات الخاصة بهيئة الزكاة السعودية.

## المشكلة التي يحلها هذا المشروع

هيئة الزكاة السعودية تقوم بفحص الموقع الجغرافي للـ IP وترفض الطلبات القادمة من خارج السعودية. هذه الخدمة تحل هذه المشكلة عبر:

```
مشاريعك في مصر → ZATCA Proxy (السعودية) → هيئة الزكاة
```

## المميزات

- ✅ **جسر آمن** بين الخوادم المصرية وهيئة الزكاة السعودية
- ✅ **مطابقة كاملة لـ ZATCA Phase 2**
- ✅ **API RESTful** سهل الاستخدام
- ✅ **أمان متقدم** مع API Keys وIP Whitelisting
- ✅ **مراقبة شاملة** وتسجيل العمليات
- ✅ **أداء عالي** مع Redis Caching
- ✅ **معالجة أخطاء متقدمة**
- ✅ **إحصائيات مفصلة**

## المتطلبات

- PHP 8.2+
- Laravel 11.x أو 12.x
- MySQL/PostgreSQL
- Redis
- خادم في السعودية
- شهادات ZATCA صالحة

## التثبيت

### 1. تحميل المشروع

```bash
git clone https://github.com/your-repo/zatca-proxy-service.git
cd zatca-proxy-service
composer install
```

### 2. إعداد البيئة

```bash
cp .env.example .env
php artisan key:generate
```

### 3. إعداد قاعدة البيانات

```bash
php artisan migrate
```

### 4. إعداد متغيرات البيئة

```env
# Application
APP_NAME="ZATCA Proxy Service"
APP_ENV=production
APP_URL=https://your-zatca-proxy.sa

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=zatca_proxy
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# ZATCA Configuration
ZATCA_ENVIRONMENT=production

# API Security
API_SECRET_KEY=your-super-secret-api-key-here
ALLOWED_CLIENT_IPS=your.egyptian.server.ip,another.allowed.ip
REQUIRE_API_KEY=true

# Performance
CACHE_ZATCA_RESPONSES=true
ENABLE_COMPRESSION=true
```

## استخدام الـ API

### 1. تقرير فاتورة إلى هيئة الزكاة

```bash
POST /api/zatca/report
```

**Headers:**
```
Content-Type: application/json
X-API-Key: your-api-key
X-Client-ID: your-client-identifier
```

**Request Body:**
```json
{
  "invoice": {
    "invoice_number": "INV-2024-001",
    "created_at": "2024-01-15T10:30:00Z",
    "sub_total": 100.00,
    "total_tax_amount": 15.00,
    "total": 115.00,
    "payment_method": "cash",
    "items": [
      {
        "name": "منتج 1",
        "quantity": 2,
        "price": 50.00,
        "amount": 100.00,
        "tax_amount": 15.00,
        "tax_percentage": 15.00
      }
    ]
  },
  "company": {
    "company_name": "شركة المثال",
    "vat_number": "300000000000003",
    "commercial_registration": "1010123457",
    "address": "شارع الملك فهد",
    "city": "الرياض",
    "zip_code": "12345",
    "zatca_certificate": "your-certificate-here",
    "zatca_private_key": "your-private-key-here",
    "zatca_secret": "your-secret-here"
  },
  "environment": "simulation"
}
```

**Response:**
```json
{
  "success": true,
  "request_id": "uuid-here",
  "data": {
    "success": true,
    "zatca_uuid": "generated-uuid",
    "zatca_hash": "generated-hash",
    "zatca_qr_code": "qr-code-string",
    "zatca_status": "reported",
    "zatca_reported_at": "2024-01-15T10:30:15Z"
  },
  "timestamp": "2024-01-15T10:30:15Z"
}
```

### 2. توليد QR Code

```bash
POST /api/zatca/qr-code
```

**Request Body:**
```json
{
  "invoice": {
    "created_at": "2024-01-15T10:30:00Z",
    "total": 115.00,
    "total_tax_amount": 15.00
  },
  "company": {
    "company_name": "شركة المثال",
    "vat_number": "300000000000003"
  }
}
```

### 3. فحص حالة الطلب

```bash
GET /api/zatca/status/{request-id}
```

### 4. إحصائيات الخدمة

```bash
GET /api/zatca/stats
```

### 5. فحص صحة الخدمة

```bash
GET /api/health
```

## استخدام الخدمة من مشاريعك المصرية

### مثال بـ PHP/Laravel

```php
<?php

use Illuminate\Support\Facades\Http;

class ZatcaProxyClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.zatca_proxy.url');
        $this->apiKey = config('services.zatca_proxy.api_key');
    }

    public function reportInvoice(array $invoiceData, array $companyData, string $environment = 'simulation'): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'X-Client-ID' => config('app.name'),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->baseUrl . '/api/zatca/report', [
            'invoice' => $invoiceData,
            'company' => $companyData,
            'environment' => $environment,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('ZATCA Proxy Error: ' . $response->body());
    }

    public function generateQrCode(array $invoiceData, array $companyData): string
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'X-Client-ID' => config('app.name'),
        ])->post($this->baseUrl . '/api/zatca/qr-code', [
            'invoice' => $invoiceData,
            'company' => $companyData,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['data']['qr_code'];
        }

        throw new Exception('QR Code Generation Error: ' . $response->body());
    }

    public function getRequestStatus(string $requestId): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->get($this->baseUrl . '/api/zatca/status/' . $requestId);

        return $response->json();
    }
}

// الاستخدام
$zatcaClient = new ZatcaProxyClient();

try {
    $result = $zatcaClient->reportInvoice($invoiceData, $companyData, 'simulation');
    
    if ($result['success']) {
        // حفظ بيانات ZATCA في قاعدة البيانات
        $order->update([
            'zatca_uuid' => $result['data']['zatca_uuid'],
            'zatca_hash' => $result['data']['zatca_hash'],
            'zatca_qr_code' => $result['data']['zatca_qr_code'],
            'zatca_status' => 'reported',
            'zatca_reported_at' => now(),
        ]);
        
        echo "تم تقرير الفاتورة بنجاح!";
    }
} catch (Exception $e) {
    Log::error('ZATCA Error: ' . $e->getMessage());
}
```

### إعداد الـ Config في مشروعك المصري

```php
// config/services.php
'zatca_proxy' => [
    'url' => env('ZATCA_PROXY_URL', 'https://your-zatca-proxy.sa'),
    'api_key' => env('ZATCA_PROXY_API_KEY'),
],
```

```env
# .env في مشروعك المصري
ZATCA_PROXY_URL=https://your-zatca-proxy.sa
ZATCA_PROXY_API_KEY=your-api-key-here
```

## الأمان

### 1. API Key Authentication
كل طلب يجب أن يحتوي على API Key صالح في الـ header:
```
X-API-Key: your-api-key
```

### 2. IP Whitelisting
يمكن تحديد عناوين IP المسموح لها بالوصول للخدمة:
```env
ALLOWED_CLIENT_IPS=192.168.1.100,203.0.113.50
```

### 3. Rate Limiting
حماية من الطلبات المفرطة (1000 طلب في الدقيقة افتراضياً).

### 4. Request Logging
تسجيل شامل لجميع الطلبات والاستجابات للمراقبة والتدقيق.

## المراقبة والإحصائيات

### إحصائيات الخدمة
```bash
GET /api/zatca/stats?include_daily=true
```

### فحص الصحة
```bash
GET /api/health
```

### السجلات
جميع العمليات يتم تسجيلها في:
- `storage/logs/laravel.log`
- قاعدة البيانات (جدول `zatca_requests`)

## النشر على الخادم السعودي

### 1. متطلبات الخادم
- Ubuntu 20.04+ أو CentOS 8+
- PHP 8.2+ مع extensions مطلوبة
- Nginx أو Apache
- MySQL 8.0+ أو PostgreSQL 13+
- Redis 6.0+
- SSL Certificate

### 2. إعداد Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name your-zatca-proxy.sa;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    root /var/www/zatca-proxy/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
```

### 3. إعداد Supervisor للـ Queue

```ini
[program:zatca-proxy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zatca-proxy/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/zatca-proxy/storage/logs/worker.log
stopwaitsecs=3600
```

## الاختبار

```bash
# تشغيل الاختبارات
php artisan test

# اختبار الاتصال مع ZATCA
curl -X GET https://your-zatca-proxy.sa/api/health

# اختبار API
curl -X POST https://your-zatca-proxy.sa/api/zatca/qr-code \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{"invoice":{"created_at":"2024-01-15T10:30:00Z","total":115.00,"total_tax_amount":15.00},"company":{"company_name":"Test Company","vat_number":"300000000000003"}}'
```

## استكشاف الأخطاء

### مشاكل شائعة:

1. **خطأ في الاتصال مع ZATCA**
   - تأكد من أن الخادم في السعودية
   - تحقق من شهادات ZATCA
   - راجع سجلات الأخطاء

2. **خطأ في المصادقة**
   - تأكد من صحة API Key
   - تحقق من IP Whitelist

3. **خطأ في البيانات**
   - راجع validation rules
   - تأكد من اكتمال بيانات الفاتورة والشركة

### السجلات المفيدة:
```bash
# سجلات Laravel
tail -f storage/logs/laravel.log

# سجلات Nginx
tail -f /var/log/nginx/error.log

# سجلات قاعدة البيانات
SELECT * FROM zatca_requests WHERE status = 'failed' ORDER BY created_at DESC LIMIT 10;
```

## الدعم والمساهمة

للدعم الفني أو المساهمة في تطوير المشروع:
- إنشاء Issue على GitHub
- إرسال Pull Request
- التواصل عبر البريد الإلكتروني

## الترخيص

هذا المشروع مرخص تحت رخصة MIT - راجع ملف [LICENSE.md](LICENSE.md) للتفاصيل.

---

**ملاحظة مهمة:** تأكد من نشر هذه الخدمة على خادم داخل السعودية للحصول على أفضل أداء وتجنب مشاكل الـ IP geolocation مع هيئة الزكاة.