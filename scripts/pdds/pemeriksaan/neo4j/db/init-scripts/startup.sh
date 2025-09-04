#!/bin/bash
set -e

echo "=== Starting Neo4j with Northwind initialization ==="

# Start Neo4j in background
echo "Starting Neo4j server..."
/docker-entrypoint.sh neo4j &
NEO4J_PID=$!

# Wait for Neo4j to be ready
echo "Waiting for Neo4j to be ready..."
while ! cypher-shell -u neo4j -p password123 "RETURN 1" >/dev/null 2>&1; do
    echo "Neo4j starting..."
    sleep 5
done
echo "Neo4j is ready!"

# Check existing data
echo "Checking existing data..."
NODE_COUNT=$(cypher-shell -u neo4j -p password123 "MATCH (n) RETURN count(n)" --format plain 2>/dev/null | tail -1 | tr -d ' ')

if [ -z "$NODE_COUNT" ] || [ "$NODE_COUNT" = "0" ]; then
    echo "No data found. Loading Northwind dataset..."
    if cypher-shell -u neo4j -p password123 -f /scripts/load_northwind.cypher; then
        echo "✅ Northwind data loaded successfully!"
        echo "Data summary:"
        cypher-shell -u neo4j -p password123 "MATCH (n) RETURN labels(n)[0] as NodeType, count(n) as Count ORDER BY NodeType"
    else
        echo "❌ Failed to load Northwind data"
    fi
else
    echo "📊 Data already exists ($NODE_COUNT nodes), skipping initialization"
fi

echo "=== Neo4j ready for connections ==="
echo "Browser: http://localhost:7474"
echo "Bolt: bolt://localhost:7687"

# Keep Neo4j running in foreground
wait $NEO4J_PID