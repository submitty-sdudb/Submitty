/**
 * Wrapper around the builtin WebSocket class. This provides us
 * with two major benefits:
 *  1. The websocket will attempt to reconnect if the connection closes
 *  2. It willl automatically perform JSON.parse/JSON.stringify on
 *      incoming/outgoing messages.
 *
 * Example usage would be:
 *
 * const client = WebSocketClient();
 * client.onmessage = (msg) => {
 *   // do something with msg
 * }
 * client.onopen = () => {
 *   client.send({
 *     type: 'getMessages'
 *   });
 * }
 * client.open();
 *
 * Due to the reconnecting strategy, the onopen method will
 * get called on the first connect and then subsequent reconnects.
 */
class WebSocketClient {
  constructor() {
    this.number = 0;
    this.autoReconnectInterval = 5 * 1000;
    this.onopen = null;
    this.onmessage = null;
  }

  open(url) {
    if (!url) {
      url = document.body.dataset.baseUrl.replace('http', 'ws') + 'ws/';
    }
    this.url = url;
    console.log(`WebSocket: connecting to ${this.url}`);
    this.client = new WebSocket(this.url);
    this.client.onopen = () => {
      console.log(`WebSocket: connected;`);
      if (this.onopen) {
        this.onopen();
      }
    };

    this.client.onmessage = (event) => {
      this.number++;
      if (this.onmessage) {
        try {
          this.onmessage(JSON.parse(event.data));
        }
        catch (exc) {
          console.error(`error on message: ${exc}`);
        }
      }
    };

    this.client.onclose = (event) => {
      switch (event.code) {
        case 1000:
          console.log('WebSocket: Closed');
          break;
        default:
          this.reconnect();
          break;
      }
      //this.onclose(event);
    };

    this.client.onerror = (error) => {
      switch(error.code) {
        case 'ECONNREFUSED':
          this.reconnect();
          break;
        default:
          //console.log(`WebSocket: Error - ${error.code}`);
          //this.onerror(error);
          break;
      }
    };
  }

  send(data) {
    this.client.send(JSON.stringify(data));
  }

  removeClientListeners() {
    this.client.onopen = null;
    this.client.onmessage = null;
    this.client.onclose = null;
    this.client.onerror = null;
  }

  reconnect() {
    console.log(`WebSocketClient: Retry in ${this.autoReconnectInterval}ms`);
    this.removeClientListeners();
    setTimeout(() => {
      console.log("WebSocketClient: Reconnecting...");
      this.open(this.url);
	  }, this.autoReconnectInterval);
  }
}
