// Prisma configuration file
// This file configures Prisma for the badminton backend application

import { config } from 'dotenv';

// Load environment variables
config();

export default {
  schema: './prisma/schema.prisma',
  migrations: {
    path: './prisma/migrations',
  },
  datasource: {
    url: process.env.DATABASE_URL,
  },
  generators: [
    {
      name: 'client',
      provider: 'prisma-client-js',
      output: './generated',
      config: {
        module: 'commonjs',
      },
    },
  ],
};
