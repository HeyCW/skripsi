// load_northwind.cypher
// Northwind Database Initialization Script for Docker

// Clear existing data
MATCH (n) DETACH DELETE n;

// Load Products from CSV
LOAD CSV WITH HEADERS FROM "https://data.neo4j.com/northwind/products.csv" AS row
CREATE (n:Product)
SET n = row,
n.unitPrice = toFloat(row.unitPrice),
n.unitsInStock = toInteger(row.unitsInStock), 
n.unitsOnOrder = toInteger(row.unitsOnOrder),
n.reorderLevel = toInteger(row.reorderLevel), 
n.discontinued = (row.discontinued <> "0");

// Load Categories from CSV
LOAD CSV WITH HEADERS FROM "https://data.neo4j.com/northwind/categories.csv" AS row
CREATE (n:Category)
SET n = row;

// Load Suppliers from CSV
LOAD CSV WITH HEADERS FROM "https://data.neo4j.com/northwind/suppliers.csv" AS row
CREATE (n:Supplier)
SET n = row;

// Create Indexes for better performance
CREATE INDEX product_id_index FOR (p:Product) ON (p.productID);
CREATE INDEX product_name_index FOR (p:Product) ON (p.productName);
CREATE INDEX category_id_index FOR (c:Category) ON (c.categoryID);
CREATE INDEX supplier_id_index FOR (s:Supplier) ON (s.supplierID);

// Create SUPPLIES relationships (Supplier -> Product)
MATCH (s:Supplier), (p:Product)
WHERE s.supplierID = p.supplierID
CREATE (s)-[:SUPPLIES]->(p);

// Create PART_OF relationships (Product -> Category)
MATCH (p:Product), (c:Category)
WHERE p.categoryID = c.categoryID
CREATE (p)-[:PART_OF]->(c);

// Verify data loading
MATCH (s:Supplier) 
WITH count(s) as supplierCount
MATCH (p:Product) 
WITH count(p) as productCount, supplierCount
MATCH (c:Category) 
WITH count(c) as categoryCount, productCount, supplierCount
MATCH ()-[r:SUPPLIES]->() 
WITH count(r) as suppliesCount, categoryCount, productCount, supplierCount
MATCH ()-[r2:PART_OF]->() 
RETURN 
  supplierCount as `Suppliers Loaded`,
  productCount as `Products Loaded`, 
  categoryCount as `Categories Loaded`,
  suppliesCount as `SUPPLIES Relationships`,
  count(r2) as `PART_OF Relationships`;

// Show sample data
MATCH (s:Supplier)-[:SUPPLIES]->(p:Product)-[:PART_OF]->(c:Category)
RETURN s.companyName as Supplier, p.productName as Product, c.categoryName as Category
LIMIT 5;