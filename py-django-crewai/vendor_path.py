"""
Bootstrap module to add the vendor directory to Python's path.
This should be imported at the beginning of your application.
"""
import sys
import os

# Add vendor directory to Python path
vendor_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'vendor')
if os.path.exists(vendor_dir) and vendor_dir not in sys.path:
    sys.path.insert(0, vendor_dir)
    print(f"Added vendor directory to Python path: {vendor_dir}")
