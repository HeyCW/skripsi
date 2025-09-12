#!/bin/bash

# Exit on any error
set -e

echo "Starting Terraform deployment..."

# Set up Terraform plugin cache to save space in AWS CloudShell
echo "Setting up Terraform plugin cache..."
export TF_PLUGIN_CACHE_DIR=/tmp/terraform-plugins
mkdir -p $TF_PLUGIN_CACHE_DIR

# Clean up any previous terraform state (optional)
# echo " Cleaning up previous terraform cache..."
# rm -rf .terraform*

# Initialize Terraform
echo "Initializing Terraform..."
terraform init

# Validate terraform configuration
echo "Validating Terraform configuration..."
terraform validate

# Plan the deployment
echo "Planning Terraform deployment..."
terraform plan -out=tfplan

# Ask for confirmation before apply
echo "Do you want to apply these changes? (y/N)"
read -r response
case $response in
    [yY][eE][sS]|[yY])
        echo "Applying Terraform changes..."
        terraform apply tfplan
        echo "Deployment completed successfully!"
        
    *)
        echo "Deployment cancelled by user"
        rm -f tfplan
        exit 1
        ;;
esac

# Clean up plan file
rm -f tfplan

echo "Build script completed!"