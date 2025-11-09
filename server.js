// server/chatServer.js
const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const cors = require("cors");

const app = express();
app.use(cors());

const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: "*", // Replace * with actual domain for production
    methods: ["GET", "POST"]
  }
});

let waitingUser = null;

io.on("connection", (socket) => {
  console.log("User connected:", socket.id);

  if (waitingUser) {
    const room = `room-${waitingUser.id}-${socket.id}`;
    socket.join(room);
    waitingUser.join(room);

    io.to(room).emit("match", { room });
    waitingUser = null;
  } else {
    waitingUser = socket;
    socket.emit("waiting");
  }

  socket.on("message", ({ room, text }) => {
    socket.to(room).emit("message", text);
  });

  socket.on("disconnect", () => {
  console.log("User disconnected:", socket.id);

  if (waitingUser && waitingUser.id === socket.id) {
    waitingUser = null;
  } else {
    // Inform the other user in the room
    const rooms = [...socket.rooms].filter((r) => r !== socket.id);
    rooms.forEach((room) => {
      socket.to(room).emit("partner-left");
    });
  }
});

});

server.listen(5000, () => {
  console.log("Chat server running on port 5000");
});
