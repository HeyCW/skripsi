#!/bin/bash
set -e

echo "🔄 Starting restaurant data import..."

# Wait for MongoDB to be fully ready
echo "⏳ Waiting for MongoDB to be ready..."
until mongosh --quiet --eval "db.adminCommand('ping')" > /dev/null 2>&1; do
    echo "   MongoDB not ready yet... waiting"
    sleep 2
done

echo "✅ MongoDB is ready!"

# Check if data already exists
COUNT=$(mongosh --quiet restaurant_db --eval "db.restaurants.countDocuments()")

if [ "$COUNT" != "0" ] 2>/dev/null && [ ! -z "$COUNT" ]; then
    echo "✅ Data already exists ($COUNT documents). Skipping import."
else
    echo "📤 Importing restaurant data from /import/restaurants.jsonl..."
    
    if [ -f "/import/restaurants.jsonl" ]; then
        mongoimport --db restaurant_db \
                   --collection restaurants \
                   --file /import/restaurants.jsonl \
                   --drop \
                   --verbose \
                   --numInsertionWorkers 4
        
        if [ $? -eq 0 ]; then
            NEW_COUNT=$(mongosh --quiet --eval "use restaurant_db; db.restaurants.countDocuments()" 2>/dev/null | tail -n1)
            echo "🎉 Import successful! Total documents imported: $NEW_COUNT"
            
            # Create indexes for better performance
            echo "🔧 Creating indexes..."
            mongosh restaurant_db --quiet --eval "
                db.restaurants.createIndex({ 'name': 1 });
                db.restaurants.createIndex({ 'cuisine': 1 });
                db.restaurants.createIndex({ 'borough': 1 });
                db.restaurants.createIndex({ 'address.zipcode': 1 });
                print('✅ Indexes created successfully');
            "
        else
            echo "❌ Import failed!"
            exit 1
        fi
    fi
fi

echo "✅ Restaurant data import process completed!"