# Base image MongoDB
FROM mongo:7.0

# Set environment variables
ENV MONGO_INITDB_DATABASE=restaurant_db

# Create directories
RUN mkdir -p /import /scripts

# Copy your data file
COPY ./data/restaurants.jsonl /import/

# Copy the import script (heredoc bisa, tapi lebih baik file terpisah)
COPY ./scripts/import-restaurants.sh /scripts/import-restaurants.sh
RUN chmod +x /scripts/import-restaurants.sh

# Expose MongoDB port
EXPOSE 27017

# Add healthcheck
HEALTHCHECK --interval=10s --timeout=5s --start-period=40s --retries=5 \
    CMD mongosh --eval "db.adminCommand('ping')" --quiet || exit 1

# Start mongod + jalankan import setiap kali container start
CMD ["bash", "-c", "mongod --bind_ip_all & sleep 5 && /scripts/import-restaurants.sh && tail -f /dev/null"]
