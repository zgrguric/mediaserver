# Varnish version 6.1.x
# 4.0 or 4.1 syntax.
vcl 4.1;
import std;

# Default backend definition. Set this to point to your content server.
backend default {
    .host = "127.0.0.1";
    .port = "8080";
	.max_connections = 1000;  # That's it

  # Probe mora biti ukljucen zbog vcl hit gdje se provjerava health of object i tako onda moze servati stale objects umjesto da se ceka na backend na istom urlu
    .probe = {
        #.url = "/"; # short easy way (GET /)
        # We prefer to only do a HEAD /
        .request =
          "HEAD / HTTP/1.1"
          "Host: localhost"
          "Connection: close"
          "User-Agent: Varnish Health Probe";

        .interval  = 15s; # check the health of each backend every 15 seconds
        .timeout   = 1s; # timing out after 1 second.
        .window    = 5;  # If 3 out of the last 5 polls succeeded the backend is considered healthy, otherwise it will be marked as sick
        .threshold = 3;
      }

	.first_byte_timeout     = 300s;   # How long to wait before we receive a first byte from our backend?
	.connect_timeout        = 5s;     # How long to wait for a backend connection?
	.between_bytes_timeout  = 2s;     # How long to wait between bytes received from our backend?
}

acl purge {
  # ACL we'll use later to allow purges
  "localhost";
  "127.0.0.1";
  "::1";
  "116.202.17.47";
}

sub vcl_recv {
  unset req.http.x-cache;
    # Happens before we check if we have this in cache already.
    #
    # Typically you clean up the request here, removing cookies you don't need,
    # rewriting the request, etc.
	#return(pass);
	# Allow purging
  if (req.method == "PURGE") {
    if (!client.ip ~ purge) { # purge is the ACL defined at the begining
      # Not from an allowed IP? Then die with an error.
      return (synth(405, "This IP is not allowed to send PURGE requests."));
    }
    # If you got this stage (and didn't error out above), purge the cached result
    return (purge);
  }
  # Only deal with "normal" types
  if (req.method != "GET" &&
      req.method != "HEAD" &&
      req.method != "PUT" &&
      req.method != "POST" &&
      req.method != "TRACE" &&
      req.method != "OPTIONS" &&
      req.method != "PATCH" &&
      req.method != "DELETE") {
    /* Non-RFC2616 or CONNECT which is weird. */
    return (pipe);
  }

  if (req.http.Upgrade ~ "(?i)websocket") {
    return (pipe);
  }

  if (req.method == "POST") {
  	return (pass);
  }

  if (req.method != "GET" && req.method != "HEAD") {
    return (pass);
  }
  # normalize accept-encoding
  if (req.http.Accept-Encoding) {
    if (req.url ~ "\.(jpg|png|gif|gz|tgz|bz2|tbz|mp3|ogg)$") {
      # No point in compressing these
      unset req.http.Accept-Encoding;
    } elsif (req.http.Accept-Encoding ~ "gzip") {
      set req.http.Accept-Encoding = "gzip";
    } elsif (req.http.Accept-Encoding ~ "deflate") {
      set req.http.Accept-Encoding = "deflate";
    } else {
      # unknown algorithm
      unset req.http.Accept-Encoding;
    }
  }

  #ignore all GET parameters for generated jpg images
  if (req.url ~ "\.jpg?.*") {
    set req.url = regsub(req.url, "\.jpg?.*", "\.jpg");
  }

  if (req.url ~ "\.(css|js|gif|jpe?g|bmp|png|tiff?|ico|img|tga|wmf|svg|swf|ico|ttf|eot|wof)$") {
    unset req.http.Cookie;
    return (hash);
  }

  # Large static files are delivered directly to the end-user without, added connection close to vcl_pipe
  # waiting for Varnish to fully read the file first.
  # Varnish 4 fully supports Streaming, so set do_stream in vcl_backend_response()
  # http://jeremiahsturgill.com/255/varnish-pipe-for-large-files/
  if (req.url ~ "^[^?]*\.(7z|avi|bz2|flac|flv|gz|mka|mkv|mov|mp3|mp4|mpeg|mpg|ogg|ogm|opus|rar|tar|tgz|tbz|txz|wav|webm|xz|zip)(\?.*)?$") {
     return(pipe);
  }

  if (req.http.Authenticate || req.http.Authorization) {
    # Not cacheable by default
    return (pass);
  }
  return (hash);
}

sub vcl_backend_response {
    # Happens after we have read the response headers from the backend.
    #
    # Here you clean the response headers, removing silly Set-Cookie headers
    # and other mistakes your backend does.

    #Keep all objects for 10 minutes beyond their TTL with a grace period of 2 minutes
    set beresp.grace = 2m;
    set beresp.keep = 8m;
}

sub vcl_pipe {
    # http://www.varnish-cache.org/ticket/451
    # This forces every pipe request to be the first one.
    set req.http.x-cache = "pipe uncacheable";
    set bereq.http.connection = "close";
}

sub vcl_pass {
	set req.http.x-cache = "pass";
}

sub vcl_synth {
	set resp.http.x-cache = "synth synth";
}

sub vcl_hash {
  # Called after vcl_recv to create a hash value for the request. This is used as a key
  # to look up the object in Varnish.

  if (req.http.host) {
     hash_data(req.http.host);
  } else {
    hash_data(server.ip);
  }
  # hash cookies for requests that have them
  #if (req.http.Cookie) {
      #hash_data(req.http.Cookie);
  #}
}

sub vcl_miss {
  # Called after a cache lookup if the requested document was not found in the cache. Its purpose
  # is to decide whether or not to attempt to retrieve the document from the backend, and which
  # backend to use.
  set req.http.x-cache = "miss";

  return (fetch);
}

sub vcl_hit {
  # Called when a cache lookup is successful.

  set req.http.x-cache = "hit";
  if (obj.ttl >= 0s) {
    # A pure unadultered hit, deliver it
    return (deliver);
  }

  # https://www.varnish-cache.org/docs/trunk/users-guide/vcl-grace.html
  # When several clients are requesting the same page Varnish will send one request to the backend and place the others on hold while fetching one copy from the backend. In some products this is called request coalescing and Varnish does this automatically.
  # If you are serving thousands of hits per second the queue of waiting requests can get huge. There are two potential problems - one is a thundering herd problem - suddenly releasing a thousand threads to serve content might send the load sky high. Secondly - nobody likes to wait. To deal with this we can instruct Varnish to keep the objects in cache beyond their TTL and to serve the waiting requests somewhat stale content.

  # We have no fresh fish. Lets look at the stale ones.
  if (std.healthy(req.backend_hint)) {
    # Backend is healthy. Limit age to 10s.
    if (obj.ttl + 10s > 0s) {
      # Object is in grace, deliver it. Automatically triggers a background fetch.
      return (deliver);
    }
  } else {
    # backend is sick - use full grace
    if (obj.ttl + obj.grace > 0s) {
      #set req.http.grace = "full";
      return (deliver);
    }
  }
}

sub vcl_deliver {
    # Happens when we have all the pieces we need, and are about to send the
    # response to the client.
    #
    # You can do accounting or modifying the final object here.
    if (obj.uncacheable) {
      set req.http.x-cache = req.http.x-cache + " uncacheable" ;
    } else {
      set req.http.x-cache = req.http.x-cache + " cacheable" ;
    }
    # uncomment the following line to show the information in the response
    set resp.http.x-cache = req.http.x-cache;
    return (deliver);
}
