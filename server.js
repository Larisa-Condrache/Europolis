const express = require('express');
const swaggerUi = require('swagger-ui-express');
const fs = require('fs');
const YAML = require('yaml');

const app = express();
app.use(express.json());

// ===== Swagger =====
const file = fs.readFileSync('./docs/openapi.yaml', 'utf8');
const swaggerDocument = YAML.parse(file);

app.use('/api-docs', swaggerUi.serve, swaggerUi.setup(swaggerDocument));

// ===== Test route =====
app.get('/', (req, res) => {
  res.send('API is running');
});

// ===== Start server =====
const PORT = 3000;
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});