# MarketX API Documentation v1

## Overview

MarketX is a multi-vendor marketplace backend built with Laravel.

Core features:

- Authentication
- Multi Tenant System
- Vendor Management
- Stores
- Products
- Cart
- Checkout
- Orders
- Vendor Orders
- Admin Management

Base URL:

http://127.0.0.1:8000/api


# Authentication

All protected APIs require:

Authorization: Bearer {TOKEN}


# Auth APIs

## Register

POST

/auth/register


## Login

POST

/auth/login


Body:

{
    "login": "email@example.com",
    "password": "password",
    "tenant_slug": "marketx"
}


## Current User

GET

/auth/me


## Logout

POST

/auth/logout



# Public Products

## Get Products

GET

/products


## Product Details

GET

/products/{product}



# Vendor System

## Create Vendor Request

POST

/vendor-requests


Body:

{
    "business_name": "Tech Store"
}


## My Vendor Request

GET

/vendor-requests/my-request



# Stores

## Create Store

POST

/stores


## My Store

GET

/stores/my-store



# Vendor Products

## Get Products

GET

/vendor/products


## Create Product

POST

/vendor/products


Body:

{
    "name": "iPhone 15",
    "description": "Latest Apple phone",
    "base_price": 3000,
    "compare_price": 3500,
    "currency": "ILS"
}


## Update Product

PUT

/vendor/products/{product}


## Delete Product

DELETE

/vendor/products/{product}



# Cart

## Get Cart

GET

/cart


## Add Product

POST

/cart/items


Body:

{
    "product_id": 1,
    "quantity": 2
}


## Update Quantity

PUT

/cart/items/{item}


## Remove Item

DELETE

/cart/items/{item}



# Checkout

## Create Order

POST

/checkout


Flow:

Cart
↓
Order
↓
Vendor Orders
↓
Order Items



# Customer Orders

## Get Orders

GET

/orders


## Order Details

GET

/orders/{order}



# Vendor Orders

## Get Vendor Orders

GET

/vendor/orders


## Update Vendor Order Status

PUT

/vendor/orders/{vendorOrder}/status


Body:

{
    "status": "processing"
}


Statuses:

pending
confirmed
processing
shipped
completed
cancelled



# Admin APIs


# Vendor Requests

GET

/admin/vendor-requests


Approve:

POST

/admin/vendor-requests/{vendorRequest}/approve


Reject:

POST

/admin/vendor-requests/{vendorRequest}/reject



# Stores Management

GET

/admin/stores


Approve:

POST

/admin/stores/{store}/approve


Reject:

POST

/admin/stores/{store}/reject


Suspend:

POST

/admin/stores/{store}/suspend



# Products Management

Pending Products:

GET

/admin/products/pending


Approve:

POST

/admin/products/{product}/approve


Reject:

POST

/admin/products/{product}/reject



# Orders Management

Get Orders:

GET

/admin/orders


Update Status:

PUT

/admin/orders/{order}/status


Body:

{
    "status": "confirmed"
}



# Database Architecture

Tenant
 |
 Users
 |
 Stores
 |
 Products


Customer
 |
 Cart
 |
 Checkout
 |
 Order
 |
 Vendor Orders
 |
 Order Items



# User Roles

platform_admin

Full platform access.


tenant_admin

Manage tenant data.


vendor_owner

Manage store and products.


customer

Shopping and orders.



# API Version

v1


Status:

Completed MVP