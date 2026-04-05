/**
 * ZATCA Proxy Client for JavaScript/Node.js Projects
 * 
 * استخدم هذا الكلاس في مشاريع JavaScript للتواصل مع خدمة ZATCA Proxy
 */

class ZatcaProxyClient {
    constructor(config) {
        this.baseUrl = config.baseUrl;
        this.apiKey = config.apiKey;
        this.clientId = config.clientId || 'js-client';
        this.timeout = config.timeout || 60000;
    }

    /**
     * تقرير فاتورة إلى هيئة الزكاة
     */
    async reportInvoice(invoiceData, companyData, environment = 'simulation') {
        try {
            const response = await fetch(`${this.baseUrl}/api/zatca/report`, {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    invoice: invoiceData,
                    company: companyData,
                    environment: environment
                }),
                signal: AbortSignal.timeout(this.timeout)
            });

            const result = await response.json();

            if (response.ok) {
                console.log('ZATCA Invoice Reported Successfully', {
                    request_id: result.request_id,
                    zatca_uuid: result.data?.zatca_uuid,
                    invoice_number: invoiceData.invoice_number
                });
                return result;
            }

            throw new Error(`ZATCA Report Failed: ${result.message || response.statusText}`);

        } catch (error) {
            console.error('ZATCA Proxy Connection Error', {
                error: error.message,
                invoice_number: invoiceData.invoice_number
            });
            throw error;
        }
    }

    /**
     * توليد QR Code للفاتورة
     */
    async generateQrCode(invoiceData, companyData) {
        try {
            const response = await fetch(`${this.baseUrl}/api/zatca/qr-code`, {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    invoice: invoiceData,
                    company: companyData
                }),
                signal: AbortSignal.timeout(this.timeout)
            });

            const result = await response.json();

            if (response.ok) {
                return result.data.qr_code;
            }

            throw new Error(`QR Code Generation Failed: ${result.message || response.statusText}`);

        } catch (error) {
            console.error('ZATCA QR Code Error', {
                error: error.message,
                invoice_number: invoiceData.invoice_number
            });
            throw error;
        }
    }

    /**
     * فحص حالة الطلب
     */
    async getRequestStatus(requestId) {
        try {
            const response = await fetch(`${this.baseUrl}/api/zatca/status/${requestId}`, {
                method: 'GET',
                headers: this.getHeaders(),
                signal: AbortSignal.timeout(30000)
            });

            const result = await response.json();

            if (response.ok) {
                return result;
            }

            throw new Error(`Failed to get request status: ${result.message || response.statusText}`);

        } catch (error) {
            console.error('ZATCA Status Check Error', {
                error: error.message,
                request_id: requestId
            });
            throw error;
        }
    }

    /**
     * فحص صحة الخدمة
     */
    async healthCheck() {
        try {
            const response = await fetch(`${this.baseUrl}/api/health`, {
                method: 'GET',
                signal: AbortSignal.timeout(10000)
            });

            return await response.json();
        } catch (error) {
            throw new Error(`ZATCA Proxy Service is not available: ${error.message}`);
        }
    }

    /**
     * الحصول على إحصائيات الخدمة
     */
    async getStats(includeDailyStats = false) {
        try {
            let url = `${this.baseUrl}/api/zatca/stats`;
            if (includeDailyStats) {
                url += '?include_daily=true';
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: this.getHeaders(),
                signal: AbortSignal.timeout(30000)
            });

            const result = await response.json();

            if (response.ok) {
                return result;
            }

            throw new Error(`Failed to get stats: ${result.message || response.statusText}`);

        } catch (error) {
            console.error('ZATCA Stats Error', { error: error.message });
            throw error;
        }
    }

    /**
     * تحضير بيانات الفاتورة
     */
    prepareInvoiceData(order) {
        return {
            invoice_number: order.order_number,
            created_at: order.created_at,
            sub_total: parseFloat(order.sub_total),
            total_tax_amount: parseFloat(order.total_tax_amount),
            total: parseFloat(order.total),
            payment_method: order.payment_method || 'cash',
            zatca_uuid: order.zatca_uuid,
            zatca_invoice_counter: order.zatca_invoice_counter,
            zatca_previous_hash: order.zatca_previous_hash,
            items: order.items.map(item => ({
                name: item.name,
                quantity: parseFloat(item.quantity),
                price: parseFloat(item.price),
                amount: parseFloat(item.amount),
                tax_amount: parseFloat(item.tax_amount || 0),
                tax_percentage: 15.00
            }))
        };
    }

    /**
     * تحضير بيانات الشركة
     */
    prepareCompanyData(restaurant) {
        return {
            company_name: restaurant.restaurant_name,
            vat_number: restaurant.vat_number,
            commercial_registration: restaurant.commercial_registration,
            address: restaurant.address,
            city: restaurant.city,
            zip_code: restaurant.zip_code,
            zatca_certificate: restaurant.zatca_certificate,
            zatca_private_key: restaurant.zatca_private_key,
            zatca_secret: restaurant.zatca_secret
        };
    }

    /**
     * الحصول على headers المطلوبة للطلبات
     */
    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-API-Key': this.apiKey,
            'X-Client-ID': this.clientId
        };
    }
}

// مثال على الاستخدام في Node.js
async function example() {
    const zatcaClient = new ZatcaProxyClient({
        baseUrl: 'https://your-zatca-proxy.sa',
        apiKey: 'your-api-key-here',
        clientId: 'my-nodejs-app',
        timeout: 60000
    });

    try {
        // فحص صحة الخدمة
        const health = await zatcaClient.healthCheck();
        console.log('Service Health:', health);

        // تحضير بيانات الفاتورة
        const invoiceData = {
            invoice_number: 'INV-2024-001',
            created_at: new Date().toISOString(),
            sub_total: 100.00,
            total_tax_amount: 15.00,
            total: 115.00,
            payment_method: 'cash',
            items: [
                {
                    name: 'منتج تجريبي',
                    quantity: 1,
                    price: 100.00,
                    amount: 100.00,
                    tax_amount: 15.00,
                    tax_percentage: 15.00
                }
            ]
        };

        const companyData = {
            company_name: 'شركة التجربة',
            vat_number: '300000000000003',
            commercial_registration: '1010123457',
            address: 'شارع الملك فهد',
            city: 'الرياض',
            zip_code: '12345',
            zatca_certificate: 'your-certificate-here',
            zatca_private_key: 'your-private-key-here'
        };

        // تقرير الفاتورة
        const result = await zatcaClient.reportInvoice(invoiceData, companyData, 'simulation');
        console.log('ZATCA Result:', result);

        // توليد QR Code
        const qrCode = await zatcaClient.generateQrCode(invoiceData, companyData);
        console.log('QR Code:', qrCode);

    } catch (error) {
        console.error('Error:', error.message);
    }
}

// للاستخدام في المتصفح
if (typeof window !== 'undefined') {
    window.ZatcaProxyClient = ZatcaProxyClient;
}

// للاستخدام في Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ZatcaProxyClient;
}

// مثال على الاستخدام في React
/*
import ZatcaProxyClient from './zatca-proxy-client';

const useZatca = () => {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const zatcaClient = new ZatcaProxyClient({
        baseUrl: process.env.REACT_APP_ZATCA_PROXY_URL,
        apiKey: process.env.REACT_APP_ZATCA_PROXY_API_KEY,
        clientId: 'react-app'
    });

    const reportInvoice = async (invoiceData, companyData) => {
        setLoading(true);
        setError(null);
        
        try {
            const result = await zatcaClient.reportInvoice(invoiceData, companyData);
            return result;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const generateQrCode = async (invoiceData, companyData) => {
        setLoading(true);
        setError(null);
        
        try {
            const qrCode = await zatcaClient.generateQrCode(invoiceData, companyData);
            return qrCode;
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    return {
        reportInvoice,
        generateQrCode,
        loading,
        error
    };
};

export default useZatca;
*/