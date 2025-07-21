resource "aws_vpc" "aws_vpc" {
  cidr_block = var.vpc_cidr
  tags = {
    Name        = var.vpc_name
    Terraform   = "true"
    Environment = "dev"
  }
}

resource "aws_subnet" "private-subnet" {
    count = length(var.private_subnets)
   
    vpc_id            = aws_vpc.aws_vpc.id
    cidr_block        = var.private_subnets[count.index]
    availability_zone = var.vpc_azs[count.index]
    tags = {
        Name = "private-${count.index + 1}"
        Terraform = "true"
        Environment = "dev"
    }
}

resource "aws_subnet" "public-subnet" {
    count = length(var.public_subnets)
    
    vpc_id                  = aws_vpc.aws_vpc.id
    cidr_block              = var.public_subnets[count.index]
    availability_zone       = var.vpc_azs[count.index]
    map_public_ip_on_launch = true 
    tags = {
        Name = "public-${count.index + 1}"
        Terraform = "true"
        Environment = "dev"
    }
}

resource "aws_internet_gateway" "igw" {
  vpc_id = aws_vpc.aws_vpc.id
  tags = {
    Name = "${var.vpc_name}-igw"
    Terraform = "true"
    Environment = "dev"
  }
}

# Elastic IP untuk NAT Gateway yang akan digunakan di private subnet
resource "aws_eip" "nat" {
  count  = length(var.private_subnets)
  domain = "vpc"
}

# NAT Gateway untuk private subnet sehingga dapat mengakses internet
resource "aws_nat_gateway" "nat" {
  count         = length(var.private_subnets)
  allocation_id = aws_eip.nat[count.index].id
  subnet_id     = aws_subnet.public-subnet[count.index].id
}

# Route table untuk public subnet
resource "aws_route_table" "public" {
  vpc_id = aws_vpc.aws_vpc.id
  
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.igw.id
  }
}

# Route table untuk private subnet
resource "aws_route_table" "private" {
  count  = length(var.private_subnets)
  vpc_id = aws_vpc.aws_vpc.id
  
  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.nat[count.index].id
  }
}

# Route table associations for public subnets
resource "aws_route_table_association" "public" {
  count          = length(var.public_subnets)
  subnet_id      = aws_subnet.public-subnet[count.index].id
  route_table_id = aws_route_table.public.id
}

# Route table associations for private subnets
resource "aws_route_table_association" "private" {
  count          = length(var.private_subnets)
  subnet_id      = aws_subnet.private-subnet[count.index].id
  route_table_id = aws_route_table.private[count.index].id
}

resource "aws_security_group" "web" {
  name_prefix = "${var.vpc_name}-web-"
  vpc_id      = aws_vpc.aws_vpc.id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }
  
  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }
}