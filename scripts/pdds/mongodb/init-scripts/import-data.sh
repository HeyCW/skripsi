#!/bin/bash

# Auto-import script for MongoDB container initialization
# This runs automatically when container starts for the first time

echo "🍽️ Starting automatic restaurant data import..."

# Wait for MongoDB to be ready
echo "⏳ Waiting for MongoDB to be ready..."
until mongosh --eval "db.adminCommand('ping')" > /dev/null 2>&1; do
    echo "   MongoDB not ready yet, waiting..."
    sleep 2
done

echo "✅ MongoDB is ready!"

# Check if file exists
if [ -f "/import/restaurants.jsonl" ]; then
    echo "📁 Found restaurants data file"
    
    # Import the data
    echo "📤 Importing restaurant data..."
    mongoimport \
        --db hnp \
        --collection restaurants \
        --file /import/restaurants.jsonl \
        --drop \
        --verbose
    
    if [ $? -eq 0 ]; then
        echo "🎉 Restaurant data imported successfully!"
        
        # Quick verification
        COUNT=$(mongosh --quiet --eval "use hnp; db.restaurants.countDocuments()" 2>/dev/null | tail -n1)
        echo "📊 Total restaurants imported: $COUNT"
        
    else
        echo "❌ Failed to import restaurant data"
    fi
    
else
    echo "⚠️ No restaurants.jsonl file found in /import/"
    echo "💡 Add your data file to ./data/restaurants.jsonl on host"
fi

echo "✅ Initialization complete!"