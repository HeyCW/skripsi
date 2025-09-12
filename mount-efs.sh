#!/bin/bash

echo "Creating EFS for CloudShell persistent storage..."

# Create EFS
EFS_RESULT=$(aws efs create-file-system \
    --creation-token cloudshell-efs-$(date +%s) \
    --encrypted)

# Extract FileSystemId
EFS_ID=$(echo $EFS_RESULT | jq -r '.FileSystemId')
echo "Created EFS: $EFS_ID"

# Add tag
aws efs tag-resource \
    --resource-id $EFS_ID \
    --tags Key=Name,Value=cloudshell-persistent-storage

echo "Tagged EFS with name: cloudshell-persistent-storage"

# Get network info
echo "Getting network information..."
VPC_ID=$(aws ec2 describe-vpcs --filters "Name=is-default,Values=true" --query 'Vpcs[0].VpcId' --output text)
SUBNET_ID=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" --query 'Subnets[0].SubnetId' --output text)
SG_ID=$(aws ec2 describe-security-groups --filters "Name=vpc-id,Values=$VPC_ID" "Name=group-name,Values=default" --query 'SecurityGroups[0].GroupId' --output text)

echo "VPC: $VPC_ID"
echo "Subnet: $SUBNET_ID" 
echo "Security Group: $SG_ID"

# Create mount target
echo "Creating mount target..."
aws efs create-mount-target \
    --file-system-id $EFS_ID \
    --subnet-id $SUBNET_ID \
    --security-groups $SG_ID

# Allow NFS in security group
echo "Updating security group for NFS access..."
aws ec2 authorize-security-group-ingress \
    --group-id $SG_ID \
    --protocol tcp \
    --port 2049 \
    --source-group $SG_ID 2>/dev/null || echo "NFS rule might already exist"

echo "Waiting for mount target to be available..."
sleep 30

# Check mount target status
while true; do
    STATE=$(aws efs describe-mount-targets --file-system-id $EFS_ID --query 'MountTargets[0].LifeCycleState' --output text 2>/dev/null)
    echo "Mount target state: $STATE"
    if [ "$STATE" = "available" ]; then
        break
    fi
    sleep 10
done

# Install NFS utils
echo "Installing NFS utilities..."
sudo yum install -y nfs-utils

# Create mount point
echo "Creating mount point..."
sudo mkdir -p /mnt/efs

# Mount EFS
echo "Mounting EFS..."
sudo mount -t nfs4 -o nfsvers=4.1,rsize=1048576,wsize=1048576,hard,intr,timeo=600 \
    $EFS_ID.efs.us-east-1.amazonaws.com:/ /mnt/efs

# Set ownership and permissions
sudo chown cloudshell-user:cloudshell-user /mnt/efs
sudo chmod 755 /mnt/efs

# Test mount
echo "Testing EFS mount..."
echo "Hello EFS from CloudShell! Created on $(date)" > /mnt/efs/test.txt
cat /mnt/efs/test.txt

# Show mount info
echo ""
echo "EFS mounted successfully!"
echo "Mount point: /mnt/efs"
echo "EFS ID: $EFS_ID"
echo "Disk usage:"
df -h /mnt/efs

echo ""
echo "You can now use /mnt/efs for persistent storage!"
echo "To use for your project:"
echo "   cd /mnt/efs"