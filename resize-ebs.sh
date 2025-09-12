#!/bin/bash
echo "🚀 Creating Cloud9 environment..."

# Create environment
RESULT=$(aws cloud9 create-environment-ec2 \
    --name "skripsi-dev" \
    --instance-type t3.small \
    --image-id resolve:ssm:/aws/service/cloud9/amis/amazonlinux-2-x86_64 \
    --automatic-stop-time-minutes 60)

ENV_ID=$(echo $RESULT | jq -r '.environmentId')
echo "Environment ID: $ENV_ID"

# Wait for environment to be ready
echo "⏳ Waiting for environment to be ready..."
sleep 60

# Get instance ID
INSTANCE_ID=$(aws cloud9 describe-environment-status --environment-id $ENV_ID --query 'status.ec2InstanceId' --output text)
echo "Instance ID: $INSTANCE_ID"

if [ "$INSTANCE_ID" != "None" ]; then
    # Get volume ID
    VOLUME_ID=$(aws ec2 describe-instances --instance-ids $INSTANCE_ID --query 'Reservations[0].Instances[0].BlockDeviceMappings[0].Ebs.VolumeId' --output text)
    echo "Volume ID: $VOLUME_ID"
    
    # Resize volume
    echo "📏 Resizing volume to 20GB..."
    aws ec2 modify-volume --volume-id $VOLUME_ID --size 20
    
    echo "✅ Volume resize initiated!"
    echo "🌐 Open Cloud9 in AWS Console to access your environment"
    echo "Environment ID: $ENV_ID"
else
    echo "❌ Instance not ready yet, check Cloud9 console"
fi