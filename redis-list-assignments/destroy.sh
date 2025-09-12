#!/bin/bash

# Exit on any error
set -e

echo "Starting Terraform destroy..."

# Set up Terraform plugin cache (in case CloudShell was restarted)
echo "Setting up Terraform plugin cache..."
export TF_PLUGIN_CACHE_DIR=/tmp/terraform-plugins
mkdir -p $TF_PLUGIN_CACHE_DIR

# Initialize Terraform (needed if CloudShell was restarted)
echo "Initializing Terraform..."
terraform init

# Show what will be destroyed
echo "Planning Terraform destroy..."
terraform plan -destroy -out=destroy-plan

# Ask for confirmation before destroy
echo "WARNING: This will destroy ALL resources managed by Terraform!"
echo "Are you sure you want to destroy these resources? Type 'yes' to confirm:"
read -r response

if [ "$response" = "yes" ]; then
    echo "Destroying Terraform resources..."
    terraform apply destroy-plan
    echo "Destroy completed successfully!"
else
    echo "Destroy cancelled by user"
    rm -f destroy-plan
    exit 1
fi

# Clean up plan file
rm -f destroy-plan

echo "Destroy script completed!"