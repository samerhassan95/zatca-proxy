"""
ZATCA Proxy Client for Python Projects

استخدم هذا الكلاس في مشاريع Python للتواصل مع خدمة ZATCA Proxy
"""

import requests
import json
import logging
from typing import Dict, List, Optional, Any
from datetime import datetime
import time

class ZatcaProxyClient:
    def __init__(self, base_url: str, api_key: str, client_id: str = 'python-client', timeout: int = 60):
        """
        Initialize ZATCA Proxy Client
        
        Args:
            base_url: URL of ZATCA Proxy Service
            api_key: API key for authentication
            client_id: Client identifier
            timeout: Request timeout in seconds
        """
        self.base_url = base_url.rstrip('/')
        self.api_key = api_key
        self.client_id = client_id
        self.timeout = timeout
        
        # Setup logging
        logging.basicConfig(level=logging.INFO)
        self.logger = logging.getLogger(__name__)

    def _get_headers(self) -> Dict[str, str]:
        """Get headers for API requests"""
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-API-Key': self.api_key,
            'X-Client-ID': self.client_id
        }

    def _make_request(self, method: str, endpoint: str, data: Optional[Dict] = None) -> Dict[str, Any]:
        """Make HTTP request to ZATCA Proxy"""
        url = f"{self.base_url}{endpoint}"
        headers = self._get_headers()
        
        try:
            if method.upper() == 'GET':
                response = requests.get(url, headers=headers, timeout=self.timeout)
            elif method.upper() == 'POST':
                response = requests.post(url, headers=headers, json=data, timeout=self.timeout)
            else:
                raise ValueError(f"Unsupported HTTP method: {method}")
            
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.RequestException as e:
            self.logger.error(f"Request failed: {e}")
            raise Exception(f"ZATCA Proxy request failed: {str(e)}")

    def report_invoice(self, invoice_data: Dict, company_data: Dict, environment: str = 'simulation') -> Dict[str, Any]:
        """
        Report invoice to ZATCA
        
        Args:
            invoice_data: Invoice information
            company_data: Company information
            environment: ZATCA environment (developer, simulation, production)
            
        Returns:
            Dict containing the response from ZATCA Proxy
        """
        try:
            payload = {
                'invoice': invoice_data,
                'company': company_data,
                'environment': environment
            }
            
            result = self._make_request('POST', '/api/zatca/report', payload)
            
            self.logger.info(f"ZATCA Invoice Reported Successfully: {result.get('request_id')}")
            return result
            
        except Exception as e:
            self.logger.error(f"ZATCA Report Failed: {e}")
            raise

    def generate_qr_code(self, invoice_data: Dict, company_data: Dict) -> str:
        """
        Generate QR code for invoice
        
        Args:
            invoice_data: Invoice information (minimal data required)
            company_data: Company information (minimal data required)
            
        Returns:
            QR code string
        """
        try:
            payload = {
                'invoice': invoice_data,
                'company': company_data
            }
            
            result = self._make_request('POST', '/api/zatca/qr-code', payload)
            return result['data']['qr_code']
            
        except Exception as e:
            self.logger.error(f"ZATCA QR Code Generation Failed: {e}")
            raise

    def get_request_status(self, request_id: str) -> Dict[str, Any]:
        """
        Get status of a ZATCA request
        
        Args:
            request_id: Request ID returned from report_invoice
            
        Returns:
            Dict containing request status information
        """
        try:
            return self._make_request('GET', f'/api/zatca/status/{request_id}')
        except Exception as e:
            self.logger.error(f"Failed to get request status: {e}")
            raise

    def health_check(self) -> Dict[str, Any]:
        """Check service health"""
        try:
            return self._make_request('GET', '/api/health')
        except Exception as e:
            raise Exception(f"ZATCA Proxy Service is not available: {str(e)}")

    def get_stats(self, include_daily_stats: bool = False) -> Dict[str, Any]:
        """
        Get service statistics
        
        Args:
            include_daily_stats: Whether to include daily statistics
            
        Returns:
            Dict containing service statistics
        """
        try:
            endpoint = '/api/zatca/stats'
            if include_daily_stats:
                endpoint += '?include_daily=true'
            
            return self._make_request('GET', endpoint)
        except Exception as e:
            self.logger.error(f"Failed to get stats: {e}")
            raise

    def prepare_invoice_data(self, order: Dict) -> Dict[str, Any]:
        """
        Prepare invoice data from order object
        
        Args:
            order: Order dictionary containing invoice information
            
        Returns:
            Formatted invoice data for ZATCA
        """
        return {
            'invoice_number': order['order_number'],
            'created_at': order['created_at'],
            'sub_total': float(order['sub_total']),
            'total_tax_amount': float(order['total_tax_amount']),
            'total': float(order['total']),
            'payment_method': order.get('payment_method', 'cash'),
            'zatca_uuid': order.get('zatca_uuid'),
            'zatca_invoice_counter': order.get('zatca_invoice_counter'),
            'zatca_previous_hash': order.get('zatca_previous_hash'),
            'items': [
                {
                    'name': item['name'],
                    'quantity': float(item['quantity']),
                    'price': float(item['price']),
                    'amount': float(item['amount']),
                    'tax_amount': float(item.get('tax_amount', 0)),
                    'tax_percentage': 15.00
                }
                for item in order['items']
            ]
        }

    def prepare_company_data(self, restaurant: Dict) -> Dict[str, Any]:
        """
        Prepare company data from restaurant object
        
        Args:
            restaurant: Restaurant dictionary containing company information
            
        Returns:
            Formatted company data for ZATCA
        """
        return {
            'company_name': restaurant['restaurant_name'],
            'vat_number': restaurant['vat_number'],
            'commercial_registration': restaurant.get('commercial_registration'),
            'address': restaurant.get('address'),
            'city': restaurant.get('city'),
            'zip_code': restaurant.get('zip_code'),
            'zatca_certificate': restaurant['zatca_certificate'],
            'zatca_private_key': restaurant['zatca_private_key'],
            'zatca_secret': restaurant.get('zatca_secret')
        }

    def wait_for_completion(self, request_id: str, max_wait_time: int = 300, check_interval: int = 5) -> Dict[str, Any]:
        """
        Wait for request completion with polling
        
        Args:
            request_id: Request ID to monitor
            max_wait_time: Maximum time to wait in seconds
            check_interval: Time between status checks in seconds
            
        Returns:
            Final request status
        """
        start_time = time.time()
        
        while time.time() - start_time < max_wait_time:
            status = self.get_request_status(request_id)
            
            if status['status'] in ['completed', 'failed', 'error']:
                return status
            
            self.logger.info(f"Request {request_id} still processing...")
            time.sleep(check_interval)
        
        raise Exception(f"Request {request_id} did not complete within {max_wait_time} seconds")


# Example usage
def example_usage():
    """Example of how to use the ZATCA Proxy Client"""
    
    # Initialize client
    client = ZatcaProxyClient(
        base_url='https://your-zatca-proxy.sa',
        api_key='your-api-key-here',
        client_id='python-example'
    )
    
    try:
        # Check service health
        health = client.health_check()
        print(f"Service Health: {health}")
        
        # Prepare sample data
        invoice_data = {
            'invoice_number': 'INV-2024-001',
            'created_at': datetime.now().isoformat(),
            'sub_total': 100.00,
            'total_tax_amount': 15.00,
            'total': 115.00,
            'payment_method': 'cash',
            'items': [
                {
                    'name': 'منتج تجريبي',
                    'quantity': 1,
                    'price': 100.00,
                    'amount': 100.00,
                    'tax_amount': 15.00,
                    'tax_percentage': 15.00
                }
            ]
        }
        
        company_data = {
            'company_name': 'شركة التجربة',
            'vat_number': '300000000000003',
            'commercial_registration': '1010123457',
            'address': 'شارع الملك فهد',
            'city': 'الرياض',
            'zip_code': '12345',
            'zatca_certificate': 'your-certificate-here',
            'zatca_private_key': 'your-private-key-here'
        }
        
        # Generate QR code
        qr_code = client.generate_qr_code(invoice_data, company_data)
        print(f"QR Code: {qr_code}")
        
        # Report invoice
        result = client.report_invoice(invoice_data, company_data, 'simulation')
        print(f"Report Result: {result}")
        
        # Check request status
        if result.get('request_id'):
            status = client.get_request_status(result['request_id'])
            print(f"Request Status: {status}")
        
        # Get service statistics
        stats = client.get_stats(include_daily_stats=True)
        print(f"Service Stats: {stats}")
        
    except Exception as e:
        print(f"Error: {e}")


# Django integration example
class DjangoZatcaIntegration:
    """Example integration with Django models"""
    
    def __init__(self):
        from django.conf import settings
        
        self.client = ZatcaProxyClient(
            base_url=settings.ZATCA_PROXY_URL,
            api_key=settings.ZATCA_PROXY_API_KEY,
            client_id=settings.ZATCA_PROXY_CLIENT_ID
        )
    
    def report_order_to_zatca(self, order):
        """Report Django order model to ZATCA"""
        try:
            # Prepare data from Django models
            invoice_data = {
                'invoice_number': order.order_number,
                'created_at': order.created_at.isoformat(),
                'sub_total': float(order.sub_total),
                'total_tax_amount': float(order.total_tax_amount),
                'total': float(order.total),
                'payment_method': order.payment_method,
                'items': [
                    {
                        'name': item.name,
                        'quantity': float(item.quantity),
                        'price': float(item.price),
                        'amount': float(item.amount),
                        'tax_amount': float(item.tax_amount or 0),
                        'tax_percentage': 15.00
                    }
                    for item in order.items.all()
                ]
            }
            
            company_data = {
                'company_name': order.restaurant.name,
                'vat_number': order.restaurant.vat_number,
                'commercial_registration': order.restaurant.commercial_registration,
                'address': order.restaurant.address,
                'city': order.restaurant.city,
                'zatca_certificate': order.restaurant.zatca_certificate,
                'zatca_private_key': order.restaurant.zatca_private_key,
                'zatca_secret': order.restaurant.zatca_secret
            }
            
            # Report to ZATCA
            result = self.client.report_invoice(invoice_data, company_data)
            
            # Update order with ZATCA data
            if result['success']:
                data = result['data']
                order.zatca_uuid = data.get('zatca_uuid')
                order.zatca_hash = data.get('zatca_hash')
                order.zatca_qr_code = data.get('zatca_qr_code')
                order.zatca_status = data.get('zatca_status', 'reported')
                order.zatca_reported_at = datetime.now()
                order.save()
                
                return True
            else:
                order.zatca_status = 'failed'
                order.zatca_errors = json.dumps(result.get('errors'))
                order.save()
                return False
                
        except Exception as e:
            order.zatca_status = 'error'
            order.zatca_errors = str(e)
            order.save()
            raise


if __name__ == '__main__':
    example_usage()