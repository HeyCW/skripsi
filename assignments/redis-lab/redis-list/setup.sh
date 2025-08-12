#!/bin/bash

echo "=== Redis Time Series PHP Setup Script ==="
echo "Setting up Composer and dependencies..."

# Update package list
echo "Updating package list..."
sudo apt-get update

# Install PHP and required extensions if not already installed
echo "Installing PHP and extensions..."
sudo apt-get install -y php php-cli php-json php-mbstring php-zip php-xml php-curl

# Download and install Composer if not already installed
if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
else
    echo "Composer already installed"
fi

# Verify Composer installation
echo "Checking Composer version..."
composer --version

# Create composer.json if it doesn't exist
if [ ! -f "composer.json" ]; then
    echo "Creating composer.json..."
    cat > composer.json << 'EOF'
{
    "require": {
        "predis/predis": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
EOF
fi

# Install dependencies
echo "Installing Predis dependencies..."
composer install

# Create vendor directory if it doesn't exist and install
if [ ! -d "vendor" ]; then
    echo "Running composer install to create vendor directory..."
    composer install --no-dev --optimize-autoloader
fi

echo ""
echo "=== Setup Complete! ==="

curl -sS https://raw.githubusercontent.com/HeyCW/skripsi/refs/heads/main/assignments/redis-lab/redis-timeseries/redis-list.php > redis-list.php
curl -sS https://raw.githubusercontent.com/HeyCW/skripsi/refs/heads/main/assignments/redis-lab/redis-timeseries/redis-list.html > redis-list.html
curl -sS https://raw.githubusercontent.com/HeyCW/skripsi/refs/heads/main/assignments/redis-lab/redis-timeseries/README.md > README.md
