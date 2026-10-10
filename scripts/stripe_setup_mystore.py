import requests
import json

secret = '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'
headers = {'Authorization': f'Bearer {secret}'}

# 1. List existing products in Stripe
res = requests.get('https://api.stripe.com/v1/products', headers=headers, params={'limit': 20})
products = res.json().get('data', [])
print('Existing Stripe Products:')
for p in products:
    print(f"- {p['id']}: {p['name']}")

# 2. Check if MyStore product exists, or create it
mystore_prod = next((p for p in products if 'MyStore' in p['name']), None)
if not mystore_prod:
    print('\nCreating MyStore product in Stripe...')
    res = requests.post(
        'https://api.stripe.com/v1/products',
        headers=headers,
        data={
            'name': 'MyStore',
            'description': 'Self-Hosted Multi-Outlet POS & Retail Management Platform. Perpetual License.',
            'metadata[code]': 'mystores'
        }
    )
    mystore_prod = res.json()
    print(f"Created Product: {mystore_prod['id']}")
else:
    print(f"\nFound existing MyStore product: {mystore_prod['id']}")

# 3. Check existing prices for this product
res = requests.get('https://api.stripe.com/v1/prices', headers=headers, params={'product': mystore_prod['id'], 'active': True})
prices = res.json().get('data', [])
usd50_price = next((pr for pr in prices if pr['currency'] == 'usd' and pr['unit_amount'] == 5000), None)

if usd50_price:
    print(f"Price already exists: {usd50_price['id']}")
    price_id = usd50_price['id']
else:
    print('Creating USD 50 one-time price in Stripe...')
    res = requests.post(
        'https://api.stripe.com/v1/prices',
        headers=headers,
        data={
            'product': mystore_prod['id'],
            'unit_amount': 5000,
            'currency': 'usd',
            'metadata[license]': 'mystores_perpetual'
        }
    )
    price_data = res.json()
    price_id = price_data.get('id')
    print(f"Created Price: {price_id}")

print(f"\nFINAL_STRIPE_PRICE_ID: {price_id}")
