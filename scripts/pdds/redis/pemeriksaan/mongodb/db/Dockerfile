# MongoDB Dockerfile dengan Auto Data Import
FROM mongo:7.0

# Set environment variables
ENV MONGO_INITDB_DATABASE=restaurant_db

# Create directories for data and scripts
RUN mkdir -p /import /docker-entrypoint-initdb.d

# Copy your data file
COPY ./data/restaurants.jsonl /import/

# Copy initialization script
COPY <<EOF /docker-entrypoint-initdb.d/01-import-data.js
// MongoDB initialization script
print('🔄 Starting database initialization...');

// Switch to the restaurant database
db = db.getSiblingDB('restaurant_db');

// Check if data already exists
const existingCount = db.restaurants.countDocuments();
print('📊 Existing documents count:', existingCount);

if (existingCount === 0) {
    print('📤 No existing data found. Data will be imported via init script.');
    print('✅ Database and collection structure prepared.');
} else {
    print('✅ Data already exists (' + existingCount + ' documents). Skipping import preparation.');
}
EOF

# Copy the actual import script
COPY <<EOF /docker-entrypoint-initdb.d/02-import-restaurants.sh
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
COUNT=$(mongosh --quiet --eval "use restaurant_db; db.restaurants.countDocuments()" 2>/dev/null | tail -n1)

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
    else
        echo "⚠️  Restaurant data file not found at /import/restaurants.jsonl"
        echo "📝 Creating sample data instead..."
        
        mongosh restaurant_db --quiet --eval "
            db.restaurants.insertMany([
                {
                    name: 'Sample Restaurant 1',
                    cuisine: 'Italian',
                    borough: 'Manhattan',
                    address: {
                        street: '123 Main St',
                        zipcode: '10001'
                    },
                    grades: [
                        { date: new Date(), grade: 'A', score: 12 }
                    ]
                },
                {
                    name: 'Sample Restaurant 2',
                    cuisine: 'Chinese',
                    borough: 'Brooklyn', 
                    address: {
                        street: '456 Oak Ave',
                        zipcode: '11201'
                    },
                    grades: [
                        { date: new Date(), grade: 'B', score: 18 }
                    ]
                }
            ]);
            print('✅ Sample data created');
        "
    fi
fi

echo "✅ Restaurant data import process completed!"
EOF

# Make the import script executable
RUN chmod +x /docker-entrypoint-initdb.d/02-import-restaurants.sh

# Expose MongoDB port
EXPOSE 27017

# Add healthcheck
HEALTHCHECK --interval=10s --timeout=5s --start-period=40s --retries=5 \
    CMD mongosh --eval "db.adminCommand('ping')" --quiet || exit 1

# Set working directory
WORKDIR /data

# Add labels for documentation
LABEL maintainer="your-email@domain.com"
LABEL description="MongoDB 7.0 with auto-imported restaurant data"
LABEL version="1.0"

# Default command (inherited from base mongo image)
# CMD ["mongod"]