FROM neo4j:4.4-community

# Set environment variables untuk Neo4j
ENV NEO4J_AUTH=neo4j/password123

# Memory settings  
ENV NEO4J_dbms_memory_heap_initial__size=512m \
    NEO4J_dbms_memory_heap_max__size=1G \
    NEO4J_dbms_memory_pagecache_size=512m

# Network settings
ENV NEO4J_dbms_default__listen__address=0.0.0.0 \
    NEO4J_dbms_connector_bolt_listen__address=0.0.0.0:7687 \
    NEO4J_dbms_connector_http_listen__address=0.0.0.0:7474

# APOC plugin settings
ENV NEO4JLABS_PLUGINS='["apoc"]'

# Security settings
ENV NEO4J_dbms_security_allow__csv__import__from__file__urls=true \
    NEO4J_dbms_security_procedures_unrestricted=apoc.*

# Copy Northwind dataset
COPY ./neo4j-scripts/load_northwind.cypher /scripts/load_northwind.cypher

# Copy startup script
COPY ./init-scripts/startup.sh /startup.sh
RUN chmod +x /startup.sh

# Expose ports
EXPOSE 7474 7687

# Use our custom startup
CMD ["/startup.sh"]