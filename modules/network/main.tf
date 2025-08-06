resource "aws_vpc" "aws_vpc" {
  cidr_block = var.vpc_cidr
  tags = {
    Name        = var.project_name
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
    Name        = "private-${count.index + 1}"
    Terraform   = "true"
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
    Name        = "public-${count.index + 1}"
    Terraform   = "true"
    Environment = "dev"
  }
}

resource "aws_internet_gateway" "igw" {
  vpc_id = aws_vpc.aws_vpc.id
  tags = {
    Name        = "${var.project_name}-igw"
    Terraform   = "true"
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
  name_prefix = "${var.project_name}-web-"
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

  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    from_port = 6379
    to_port   = 6379
    protocol  = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }


  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

}

resource "aws_security_group" "emr_master_sg" {
  name_prefix = "${var.project_name}-${var.environment}-emr-master-"
  vpc_id      = aws_vpc.aws_vpc.id

  # SSH access
  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = [var.vpc_cidr]
  }

  # Spark UI
  ingress {
    from_port   = 4040
    to_port     = 4040
    protocol    = "tcp"
    cidr_blocks = [var.vpc_cidr]
  }

  # Jupyter/Zeppelin
  ingress {
    from_port   = 8888
    to_port     = 8890
    protocol    = "tcp"
    cidr_blocks = [var.vpc_cidr]
  }

  # All outbound traffic
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name        = "${var.project_name}-${var.environment}-emr-master-sg"
    Environment = var.environment
    Project     = var.project_name
  }
}

resource "aws_security_group" "emr_slave_sg" {
  name_prefix = "${var.project_name}-${var.environment}-emr-slave-"
  vpc_id      = aws_vpc.aws_vpc.id

  # Communication within cluster
  ingress {
    from_port = 0
    to_port   = 65535
    protocol  = "tcp"
    self      = true
  }

  # Communication with master
  ingress {
    from_port       = 0
    to_port         = 65535
    protocol        = "tcp"
    security_groups = [aws_security_group.emr_master_sg.id]
  }

  # All outbound traffic
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name        = "${var.project_name}-${var.environment}-emr-slave-sg"
    Environment = var.environment
    Project     = var.project_name
  }
}
