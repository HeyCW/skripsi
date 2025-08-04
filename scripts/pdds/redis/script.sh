#!/bin/bash

# Logging untuk debug
exec > >(tee /var/log/user-data.log) 2>&1
echo "=== User Data Script Started at $(date) ==="

# Update package manager
echo "Updating packages..."
sudo yum update -y

# Install Docker
echo "Installing Docker..."
sudo yum install docker -y

# Start Docker service
echo "Starting Docker service..."
sudo systemctl start docker
sudo systemctl enable docker

# Add ec2-user to docker group
echo "Adding ec2-user to docker group..."
sudo usermod -a -G docker ec2-user

# Install Docker Compose plugin
echo "Installing Docker Compose..."
sudo mkdir -p /usr/local/lib/docker/cli-plugins
sudo curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64" \
  -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# Create symlink for global access
sudo ln -sf /usr/local/lib/docker/cli-plugins/docker-compose /usr/local/bin/docker-compose

# Install Git
echo "Installing Git..."
sudo yum install git -y

# Clone the repository
echo "Cloning repository..."
cd /home/ec2-user
sudo -u ec2-user git clone https://github.com/HeyCW/skripsi.git

# Change ownership and navigate to redis directory
echo "Setting up Redis..."
sudo chown -R ec2-user:ec2-user /home/ec2-user/skripsi
cd /home/ec2-user/skripsi/scripts/pdds/redis

# Start docker compose as ec2-user with proper group
echo "Starting Docker Compose..."
sudo -u ec2-user /usr/local/bin/docker-compose up -d

echo "=== User Data Script Completed at $(date) ==="