# CrewAI + Django Movie Chatbot: Vendor Dependency Deployment for Cloud Foundry

This document explains how to deploy the application using the vendor dependency approach.

## Why Use Vendor Dependencies?

The vendor approach pre-packages the essential dependencies for deployment.

## How It Works

Dependencies are pre-installed to a local `vendor` directory.

## Deployment Steps

### For Each Deployment

1. Run the deployment script:

```bash
./deploy-on-tp4cf.sh
```

This script will:

- Set up the vendor directory with dependencies
- Collect static files
- Stage the app artifact on Cloud Foundry

## Manual Steps (if needed)

If you prefer to run the steps manually:

1. Set up the vendor directory:

```bash
./setup-vendor.sh
```

2. Deploy to Cloud Foundry:

```bash
cf push
```

## Troubleshooting

- If the application fails to start, check the logs with `cf logs movie-chatbot --recent` to identify the issue.
- For dependency errors, you may need to adjust which packages are included in the vendor directory.
