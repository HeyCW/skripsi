#!/bin/bash

echo "=== Redis Time Series PHP Setup Script for AWS Cloud9 ==="
echo "Setting up Composer and dependencies..."

# Detect OS and set package manager
detect_os() {
    if command -v yum &> /dev/null; then
        echo "amazon-linux"
    elif command -v apt-get &> /dev/null; then
        echo "ubuntu"
    else
        echo "unknown"
    fi
}

OS=$(detect_os)
echo "Detected OS: $OS"

# Update package list based on OS
echo "Updating package list..."
case $OS in
    "amazon-linux")
        sudo yum update -y
        ;;
    "ubuntu")
        sudo apt-get update
        ;;
    *)
        echo "Unsupported OS. Please install PHP manually."
        exit 1
        ;;
esac

# Install PHP and required extensions
echo "Installing PHP and extensions..."
case $OS in
    "amazon-linux")
        sudo yum install -y php php-cli php-json php-mbstring php-zip php-xml php-curl php-mysql
        ;;
    "ubuntu")
        sudo apt-get install -y php php-cli php-json php-mbstring php-zip php-xml php-curl php-mysql
        ;;
esac

# Check if PHP was installed successfully
if ! command -v php &> /dev/null; then
    echo "Error: PHP installation failed!"
    exit 1
fi

echo "PHP installed successfully. Version:"
php --version

# Download and install Composer if not already installed
if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
    
    # Add composer to PATH for current session
    export PATH="$PATH:/usr/local/bin"
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
    echo "composer.json created successfully"
else
    echo "composer.json already exists"
fi

# Create src directory if it doesn't exist
if [ ! -d "src" ]; then
    echo "Creating src directory..."
    mkdir -p src
fi

# Install dependencies
echo "Installing Predis dependencies..."
composer install --no-dev --optimize-autoloader

# Verify installation
if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
    echo "Dependencies installed successfully!"
else
    echo "Error: Failed to install dependencies"
    exit 1
fi

echo ""
echo "=== Downloading project files ==="

# Download files with error checking
download_file() {
    local url=$1
    local filename=$2
    
    echo "Downloading $filename..."
    if curl -sS -f "$url" > "$filename"; then
        echo "✓ $filename downloaded successfully"
    else
        echo "✗ Failed to download $filename"
        return 1
    fi
}

# Download project files
download_file "https://raw.githubusercontent.com/HeyCW/skripsi/refs/heads/main/assignments/redis-lab/redis-timeseries/redis-list.php" "redis-list.php"
download_file "https://raw.githubusercontent.com/HeyCW/skripsi/refs/heads/main/assignments/redis-lab/redis-timeseries/redis-list.html" "redis-list.html"
download_file "https://raw.githubusercontent.com/HeyCW/skripsi/refs/heads/main/assignments/redis-lab/redis-timeseries/README.md" "README.md"

echo ""
echo "=== Setup Complete! ==="
echo "✓ PHP and extensions installed"
echo "✓ Composer installed"
echo "✓ Dependencies installed (Predis)"
echo "✓ Project files downloaded"
echo ""
echo "You can now run your PHP Redis applications!"
echo "Example: php -S 127.0.0.1:80"