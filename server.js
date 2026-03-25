require('dotenv').config();
const express = require('express');
const swaggerUi = require('swagger-ui-express');
const fs = require('fs');
const YAML = require('yaml');

const app = express();
app.use(express.json());

// Swagger
const file = fs.readFileSync('./docs/openapi.yaml', 'utf8');
const swaggerDocument = YAML.parse(file);
app.use('/docs', swaggerUi.serve, swaggerUi.setup(swaggerDocument));

// Routes
const gamesRouter = require('./routes/games');
const playersRouter = require('./routes/players');

app.use('/games', gamesRouter);
app.use('/players', playersRouter);

// Ruta de test
app.get('/', (req, res) => {
  res.send('API is running');
});

// Start server
const PORT = process.env.PORT || 3000; // citeste din env sau foloseste 3000 ca default
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});