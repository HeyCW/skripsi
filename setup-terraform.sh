#!/bin/bash

set -e

echo "Memulai instalasi Terraform..."

wget https://releases.hashicorp.com/terraform/1.9.1/terraform_1.9.1_linux_amd64.zip

unzip terraform_*.zip

mkdir -p ~/bin

mv terraform ~/bin/


