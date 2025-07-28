# Update package manager
sudo yum update -y

# Install Docker
sudo yum install docker -y

# Start Docker service
sudo systemctl start docker

# Enable Docker to start on boot
sudo systemctl enable docker

# Add ec2-user to docker group (agar tidak perlu sudo)
sudo usermod -a -G docker ec2-user

# Download Docker Compose plugin
sudo mkdir -p /usr/local/lib/docker/cli-plugins
sudo curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64" -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# Install Git
sudo yum install git -y

#Clone the repository
git clone https://github.com/HeyCW/skripsi.git

# Navigate to the redis directory
cd skripsi/scripts/pdds/redis

docker compose up