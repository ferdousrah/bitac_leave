function watchvideo(){

  let peerConnection;
  const config = {
    iceServers: [
        { 
        "urls": "stun:stun.technocratsbd.com:3478",
      },
      { 
         "urls": "turn:turn.technocratsbd.com:3478",
         "username": "guest",
         "credential": "somepassword"
       }
    ]
  };
  
  //const socket = io.connect('wss://datumresource.com/socket.io/', { transports: ['websocket'] });
  
  const socket = io.connect('wss://datumresource.com:4000', { transports: ['websocket'] });
  
  
  //const socket = io.connect('http://localhost:4000', { transports: ['websocket'] });
  const loggedflag = document.getElementById('loggedflag').value;
  
  const video = document.querySelector("#live-video");
  const enableAudioButton = document.querySelector("#enable-audio");
  const streamClosedMessage = document.querySelector("#stream-closed-message");
  const dstreamClosedMessage = document.querySelector("#default-offline-stream-closed-message");
  const offstreamClosedMessage = document.querySelector("#offline-stream-closed-message");
  
  
  
  enableAudioButton.addEventListener("click", enableAudio)
  
  socket.on("offer", (id, description) => {
    peerConnection = new RTCPeerConnection(config);
    peerConnection
      .setRemoteDescription(description)
      .then(() => peerConnection.createAnswer())
      .then(sdp => peerConnection.setLocalDescription(sdp))
      .then(() => {
        socket.emit("answer", id, peerConnection.localDescription);
      });
    peerConnection.ontrack = event => {
  
      if(loggedflag == 1){
        console.log(event.streams[0]);
        if (event.streams.length == 0 || event.streams.length == '') {
          streamClosedMessage.style.display = "block"; // hide the "Stream closed" message
        }else{
  
        video.srcObject = event.streams[0];
        streamClosedMessage.style.display = "none"; // hide the "Stream closed" message
        }
		
		}else{
  
        dstreamClosedMessage.style.display = "none"; // hide the "Stream closed" message 
        offstreamClosedMessage.style.display = "block";
      }
  
    };
    peerConnection.onicecandidate = event => {
      if (event.candidate) {
        socket.emit("candidate", id, event.candidate);
      }
    };
  });
  
  
  socket.on("candidate", (id, candidate) => {
    peerConnection
      .addIceCandidate(new RTCIceCandidate(candidate))
      .catch(e => console.error(e));
  });
  
  socket.on("connect", () => {
    socket.emit("watcher");
  });
  
  socket.on("broadcaster", () => {
    socket.emit("watcher");
    streamClosedMessage.style.display = "none"; // hide the "Stream closed" message
  });
  
  window.onunload = window.onbeforeunload = () => {
    socket.close();
    peerConnection.close();
    streamClosedMessage.style.display = "block"; // hide the "Stream closed" message
  };
  
  video.onended = () => {
    
    video.pause();
    video.srcObject = null;
    video.src = "";
  
    streamClosedMessage.style.display = "block"; // hide the "Stream closed" message
  
  };
  
  
  video.onplay = () => {
    //$('#stream-closed-message').hide();
    
  }; 
  
  // end video
  
  
  
  } // end of watchvideo()
  
  
  
  function enableAudio() {
    console.log("Enabling audio")
    video.muted = false;
  }
  
  watchvideo(); // Initialize the livestream video on page load